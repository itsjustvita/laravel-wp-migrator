<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import;

use Carbon\CarbonImmutable;
use ItsJustVita\WpMigrator\Detection\Finding;
use Throwable;

/**
 * Collects what happened during one import run and renders it as Markdown.
 */
final class ImportReport
{
    public const array OUTCOMES = ['created', 'updated', 'unchanged', 'skipped', 'failed'];

    /** How many example posts are kept per unknown shortcode or block. */
    private const int MAX_EXAMPLE_POSTS = 5;

    public readonly CarbonImmutable $startedAt;

    private ?CarbonImmutable $finishedAt = null;

    /** @var array<string, array<string, int>> */
    private array $counts = [];

    /** @var list<array{importer: string, source_id: int, message: string}> */
    private array $warnings = [];

    /** @var list<array{importer: string, source_id: int, kind: string, reference: string}> */
    private array $unresolved = [];

    /** @var list<array{importer: string, source_id: int, reason: string}> */
    private array $skipped = [];

    /** @var list<array{importer: string, source_id: int, message: string, exception: string}> */
    private array $errors = [];

    /** @var list<Finding> */
    private array $findings = [];

    /** @var list<string> */
    private array $notes = [];

    /** @var array<string, array{count: int, post_ids: list<int>}> */
    private array $unknownShortcodes = [];

    /** @var array<string, array{count: int, post_ids: list<int>}> */
    private array $unknownBlocks = [];

    public function __construct(public readonly bool $dryRun = false)
    {
        $this->startedAt = CarbonImmutable::now();
    }

    /**
     * Makes the importer show up in the summary even when it processes nothing.
     */
    public function begin(string $importer): void
    {
        $this->counts[$importer] ??= array_fill_keys(self::OUTCOMES, 0);
    }

    public function created(string $importer): void
    {
        $this->count($importer, 'created');
    }

    public function updated(string $importer): void
    {
        $this->count($importer, 'updated');
    }

    public function unchanged(string $importer): void
    {
        $this->count($importer, 'unchanged');
    }

    public function skipped(string $importer, int $sourceId, string $reason): void
    {
        $this->count($importer, 'skipped');
        $this->skipped[] = ['importer' => $importer, 'source_id' => $sourceId, 'reason' => $reason];
    }

    public function failed(string $importer, int $sourceId, Throwable $exception): void
    {
        $this->count($importer, 'failed');
        $this->errors[] = [
            'importer' => $importer,
            'source_id' => $sourceId,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ];
    }

    public function warn(string $importer, int $sourceId, string $message): void
    {
        $this->warnings[] = ['importer' => $importer, 'source_id' => $sourceId, 'message' => $message];
    }

    public function unresolved(string $importer, int $sourceId, string $kind, int|string $reference): void
    {
        $this->unresolved[] = [
            'importer' => $importer,
            'source_id' => $sourceId,
            'kind' => $kind,
            'reference' => (string) $reference,
        ];
    }

    public function unknownShortcode(string $tag, int $postId): void
    {
        $this->countUnknown($this->unknownShortcodes, $tag, $postId);
    }

    public function unknownBlock(string $name, int $postId): void
    {
        $this->countUnknown($this->unknownBlocks, $name, $postId);
    }

    /**
     * @return array<string, array{count: int, post_ids: list<int>}>
     */
    public function unknownShortcodes(): array
    {
        return $this->unknownShortcodes;
    }

    /**
     * @return array<string, array{count: int, post_ids: list<int>}>
     */
    public function unknownBlocks(): array
    {
        return $this->unknownBlocks;
    }

    /**
     * @param  list<Finding>  $findings
     */
    public function addFindings(array $findings): void
    {
        foreach ($findings as $finding) {
            if ($finding->detected) {
                $this->findings[] = $finding;
            }
        }
    }

    public function note(string $note): void
    {
        $this->notes[] = $note;
    }

    public function finish(): void
    {
        $this->finishedAt = CarbonImmutable::now();
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function counts(): array
    {
        return $this->counts;
    }

    /**
     * @return list<array{importer: string, source_id: int, message: string}>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return list<array{importer: string, source_id: int, kind: string, reference: string}>
     */
    public function unresolvedReferences(): array
    {
        return $this->unresolved;
    }

    /**
     * @return list<array{importer: string, source_id: int, reason: string}>
     */
    public function skippedItems(): array
    {
        return $this->skipped;
    }

    /**
     * @return list<array{importer: string, source_id: int, message: string, exception: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<Finding>
     */
    public function findings(): array
    {
        return $this->findings;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function toMarkdown(): string
    {
        $lines = ['# WP migration report', ''];
        $lines[] = '- Started: '.$this->startedAt->toDateTimeString();

        if ($this->finishedAt !== null) {
            $lines[] = '- Finished: '.$this->finishedAt->toDateTimeString();
        }

        $lines[] = '- Mode: '.($this->dryRun ? 'dry run, nothing was written' : 'import');

        foreach ($this->notes as $note) {
            $lines[] = '- '.$note;
        }

        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = '| Importer | Created | Updated | Unchanged | Skipped | Failed |';
        $lines[] = '| --- | ---: | ---: | ---: | ---: | ---: |';

        foreach ($this->counts as $importer => $counts) {
            $lines[] = '| '.$importer.' | '.implode(' | ', array_map(
                fn (string $outcome): int => $counts[$outcome],
                self::OUTCOMES,
            )).' |';
        }

        $this->section($lines, 'Unsupported content', array_map(
            fn (Finding $finding): string => sprintf('**%s**: %s', $finding->label, $finding->summary()),
            $this->findings,
        ));

        $this->section($lines, 'Unknown shortcodes', $this->describeUnknown($this->unknownShortcodes, '[%s]'));
        $this->section($lines, 'Blocks without a renderer', $this->describeUnknown($this->unknownBlocks, '%s'));

        $this->section($lines, 'Warnings', array_map(
            fn (array $warning): string => sprintf('%s #%d: %s', $warning['importer'], $warning['source_id'], $warning['message']),
            $this->warnings,
        ));

        $this->section($lines, 'Unresolved references', array_map(
            fn (array $ref): string => sprintf('%s #%d: %s %s', $ref['importer'], $ref['source_id'], $ref['kind'], $ref['reference']),
            $this->unresolved,
        ));

        $this->section($lines, 'Skipped', array_map(
            fn (array $item): string => sprintf('%s #%d: %s', $item['importer'], $item['source_id'], $item['reason']),
            $this->skipped,
        ));

        $this->section($lines, 'Errors', array_map(
            fn (array $error): string => sprintf('%s #%d: %s (%s)', $error['importer'], $error['source_id'], $error['message'], $error['exception']),
            $this->errors,
        ));

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $items
     */
    private function section(array &$lines, string $title, array $items): void
    {
        if ($items === []) {
            return;
        }

        $lines[] = '';
        $lines[] = sprintf('## %s (%d)', $title, count($items));
        $lines[] = '';

        foreach ($items as $item) {
            $lines[] = '- '.str_replace(["\r\n", "\n"], ' ', $item);
        }
    }

    /**
     * Writes the Markdown report into the directory and returns its path.
     */
    public function save(string $directory): string
    {
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create the report directory %s.', $directory));
        }

        $base = rtrim($directory, '/').'/wp-migrator-'.$this->startedAt->format('Y-m-d-His');
        $path = $base.'.md';

        for ($i = 2; file_exists($path); $i++) {
            $path = $base.'-'.$i.'.md';
        }

        file_put_contents($path, $this->toMarkdown());

        return $path;
    }

    /**
     * @param  array<string, array{count: int, post_ids: list<int>}>  $unknown
     * @return list<string>
     */
    private function describeUnknown(array $unknown, string $format): array
    {
        $lines = [];

        foreach ($unknown as $name => $item) {
            $lines[] = sprintf(
                '%s: %d %s, for example in %s %s',
                sprintf($format, $name),
                $item['count'],
                $item['count'] === 1 ? 'occurrence' : 'occurrences',
                count($item['post_ids']) === 1 ? 'post' : 'posts',
                implode(', ', $item['post_ids']),
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, array{count: int, post_ids: list<int>}>  $items
     */
    private function countUnknown(array &$items, string $name, int $postId): void
    {
        $items[$name] ??= ['count' => 0, 'post_ids' => []];
        $items[$name]['count']++;

        if (count($items[$name]['post_ids']) < self::MAX_EXAMPLE_POSTS && ! in_array($postId, $items[$name]['post_ids'], true)) {
            $items[$name]['post_ids'][] = $postId;
        }
    }

    private function count(string $importer, string $outcome): void
    {
        $this->begin($importer);
        $this->counts[$importer][$outcome]++;
    }
}
