<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Suspicious;

use FilesystemIterator;
use Illuminate\Config\Repository;
use Illuminate\Database\Query\Builder;
use ItsJustVita\WpMigrator\Detection\Finding;
use ItsJustVita\WpMigrator\Source\Like;
use ItsJustVita\WpMigrator\Source\WpDatabase;
use ItsJustVita\WpMigrator\Support\ExecutableFile;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Signs that the site was hacked: injected code in the content the import
 * takes over, code in options, and program files in the uploads. Nothing is
 * changed; the findings say what to check before the import.
 */
class SuspiciousContent
{
    private const array LABELS = [
        'scripts' => 'Scripts in the content',
        'event-handlers' => 'Event handlers in the content',
        'script-links' => 'Links that run script',
        'frames' => 'Frames from other sites',
        'hidden-links' => 'Hidden links',
        'obfuscation' => 'Obfuscated code',
        'options' => 'Code in options',
        'uploads' => 'Program files in the uploads',
    ];

    /** How the details of a content finding read, such as "from evil.example". */
    private const array DETAILS = [
        'scripts' => 'from %s',
        'frames' => 'from %s',
        'hidden-links' => 'to %s',
    ];

    private const array OPTION_NEEDLES = ['<script', 'eval(', 'base64_decode(', 'gzinflate(', 'str_rot13(', 'fromCharCode', 'atob('];

    private const array TRANSIENTS = ['_transient_', '_site_transient_'];

    private const int SAMPLES = 5;

    private const int MAX_UPLOAD_ENTRIES = 500_000;

    public function __construct(
        private readonly WpDatabase $db,
        private readonly Repository $config,
    ) {}

    public static function advice(string $key): string
    {
        return match ($key) {
            'options' => 'Options are not imported, but code in them is a common sign of a hacked site, so treat the rest of its content with care.',
            'uploads' => 'The import never copies them, but on a hacked site they are often backdoors; do not reuse anything else from the old server either.',
            default => 'The import keeps the HTML as it is, so the new site would serve this. Check these posts and remove what does not belong there before the import.',
        };
    }

    /**
     * The checks that found something.
     *
     * @return list<Finding>
     */
    public function findings(): array
    {
        return array_values(array_filter([...$this->content(), $this->options(), $this->uploads()]));
    }

    /**
     * @return list<Finding>
     */
    private function content(): array
    {
        $checks = new ContentChecks($this->siteHost());
        $found = [];

        $posts = $this->db->posts()
            ->whereNotIn('post_type', $this->config->array('wp-migrator.import.excluded_post_types'))
            ->whereIn('post_status', $this->config->array('wp-migrator.import.post_statuses'))
            ->select(['ID', 'post_content', 'post_excerpt']);

        foreach ($posts->lazyById(200, 'ID')->chunk(200) as $rows) {
            $ids = array_values($rows->map(fn (object $row): int => (int) $row->ID)->all());
            $elementor = $this->elementorData($ids);

            foreach ($rows as $row) {
                $id = (int) $row->ID;
                $html = $row->post_content."\n".$row->post_excerpt."\n".($elementor[$id] ?? '');

                foreach ($checks->find($html) as $key => $details) {
                    $found[$key] ??= ['ids' => [], 'details' => []];
                    $found[$key]['ids'][] = $id;
                    array_push($found[$key]['details'], ...$details);
                }
            }
        }

        $findings = [];

        foreach (ContentChecks::KEYS as $key) {
            if (! isset($found[$key])) {
                continue;
            }

            $details = implode(', ', array_slice(array_values(array_unique($found[$key]['details'])), 0, self::SAMPLES));

            $findings[] = Finding::posts(
                $key,
                self::LABELS[$key],
                count($found[$key]['ids']),
                array_slice($found[$key]['ids'], 0, self::SAMPLES),
                sprintf(self::DETAILS[$key] ?? '%s', $details),
            );
        }

        return $findings;
    }

    /**
     * The text of the Elementor data of the given posts: every string in it,
     * or the raw data when it cannot be read.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function elementorData(array $ids): array
    {
        $rows = $this->db->table('postmeta')
            ->whereIn('post_id', $ids)
            ->where('meta_key', '_elementor_data')
            ->get(['post_id', 'meta_value']);

        $text = [];

        foreach ($rows as $row) {
            $raw = (string) $row->meta_value;

            try {
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $data = null;
            }

            $strings = [];

            if (is_array($data)) {
                array_walk_recursive($data, function (mixed $value) use (&$strings): void {
                    if (is_string($value)) {
                        $strings[] = $value;
                    }
                });
            } else {
                $strings[] = $raw;
            }

            $text[(int) $row->post_id] = ($text[(int) $row->post_id] ?? '').implode("\n", $strings);
        }

        return $text;
    }

    private function options(): ?Finding
    {
        $names = $this->db->table('options')
            ->where(function (Builder $query): void {
                foreach (self::OPTION_NEEDLES as $needle) {
                    $query->orWhere(fn (Builder $inner) => Like::contains($inner, 'option_value', $needle));
                }
            })
            ->orderBy('option_name')
            ->pluck('option_name')
            ->map(fn (mixed $name): string => (string) $name)
            ->reject(fn (string $name): bool => array_any(self::TRANSIENTS, fn (string $prefix): bool => str_starts_with($name, $prefix)))
            ->values()
            ->all();

        if ($names === []) {
            return null;
        }

        return Finding::present('options', self::LABELS['options'], $this->list(array_values($names), 'option', 'options'));
    }

    private function uploads(): ?Finding
    {
        $root = $this->config->get('wp-migrator.source.uploads_path');

        if (! is_string($root) || $root === '' || ! is_dir($root) || ! is_readable($root)) {
            return null;
        }

        $root = rtrim((string) realpath($root), DIRECTORY_SEPARATOR);
        $files = [];
        $seen = 0;

        try {
            $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

            foreach ($entries as $entry) {
                if (++$seen > self::MAX_UPLOAD_ENTRIES) {
                    break;
                }

                if ($entry instanceof SplFileInfo && $entry->isFile() && ExecutableFile::isCode($entry->getFilename()) && ! $this->isSilentIndex($entry)) {
                    $files[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($entry->getPathname(), strlen($root) + 1));
                }
            }
        } catch (UnexpectedValueException) {
            // An unreadable directory ends the walk; what was found so far still counts.
        }

        if ($files === []) {
            return null;
        }

        sort($files);

        return Finding::present('uploads', self::LABELS['uploads'], $this->list($files, 'file', 'files'));
    }

    /**
     * The empty index.php that WP and many plugins put into directories. A
     * file that cannot be read, for example because a virus scanner blocks
     * it, does not count as one.
     */
    private function isSilentIndex(SplFileInfo $file): bool
    {
        if ($file->getFilename() !== 'index.php' || $file->getSize() > 200) {
            return false;
        }

        $content = @file_get_contents($file->getPathname());

        return is_string($content) && str_contains($content, 'Silence is golden');
    }

    /**
     * @param  list<string>  $items
     */
    private function list(array $items, string $one, string $many): string
    {
        $shown = array_slice($items, 0, self::SAMPLES);

        return sprintf(
            '%d %s: %s%s',
            count($items),
            count($items) === 1 ? $one : $many,
            implode(', ', $shown),
            count($items) > count($shown) ? ', …' : '',
        );
    }

    private function siteHost(): ?string
    {
        $home = $this->db->option('home');
        $host = is_string($home) ? parse_url($home, PHP_URL_HOST) : null;

        return is_string($host) ? strtolower($host) : null;
    }
}
