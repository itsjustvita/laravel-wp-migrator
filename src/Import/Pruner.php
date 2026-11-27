<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Import;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ItsJustVita\WpMigrator\Database\Tables;
use ItsJustVita\WpMigrator\Import\Importers\NavigationImporter;
use ItsJustVita\WpMigrator\Models\Comment;
use ItsJustVita\WpMigrator\Models\ImportMapEntry;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Models\Menu;
use ItsJustVita\WpMigrator\Models\MenuItem;
use ItsJustVita\WpMigrator\Models\Post;
use ItsJustVita\WpMigrator\Models\Redirect;
use ItsJustVita\WpMigrator\Models\SeoMeta;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\Redirects\OldUrls;
use ItsJustVita\WpMigrator\Redirects\RedirectPath;
use ItsJustVita\WpMigrator\Source\Readers\CommentReader;
use ItsJustVita\WpMigrator\Source\Readers\MediaReader;
use ItsJustVita\WpMigrator\Source\Readers\MenuItemReader;
use ItsJustVita\WpMigrator\Source\Readers\MenuReader;
use ItsJustVita\WpMigrator\Source\Readers\PostFilter;
use ItsJustVita\WpMigrator\Source\Readers\PostReader;
use ItsJustVita\WpMigrator\Source\Readers\TermFilter;
use ItsJustVita\WpMigrator\Source\Readers\TermReader;
use ItsJustVita\WpMigrator\Source\Readers\UserReader;
use Throwable;

/**
 * Removes what earlier runs imported and the import would no longer read:
 * rows that were deleted or trashed on the old site, or that the
 * configuration leaves out since. The whole import map is compared with the
 * whole site, whatever the run itself was limited to.
 *
 * Users are reported and never deleted, since the accounts belong to the
 * application. Records the import linked instead of creating are left alone.
 */
class Pruner
{
    /** The order things are removed in: what refers to other records goes first. */
    private const array KINDS = ['comments', 'menu items', 'menus', 'posts', 'terms', 'media'];

    private const int CHUNK = 500;

    public function __construct(
        private readonly PostReader $posts,
        private readonly MediaReader $media,
        private readonly MenuItemReader $menuItems,
        private readonly TermReader $terms,
        private readonly MenuReader $menus,
        private readonly UserReader $users,
        private readonly CommentReader $comments,
        private readonly OldUrls $oldUrls,
        private readonly ImportMap $map,
        private readonly Repository $config,
        private readonly FilesystemFactory $filesystems,
    ) {}

    /**
     * @param  (Closure(array<string, int>): bool)|null  $confirm  asked before anything is removed, with the number of records of each kind
     */
    public function run(ImportReport $report, ImportProgress $progress, bool $dryRun, ?Closure $confirm = null): void
    {
        /** @var array<string, list<array{ImportMapEntry, Model}>> $found */
        $found = [];

        foreach ($this->stale() as $entry) {
            $target = $entry->linked || $this->stillMapped($entry) ? null : $this->map->target($entry);

            if ($target === null) {
                // Nothing to remove: the record is gone already, was the application's own, or is still needed.
                $entry->delete();

                continue;
            }

            if ($entry->source_type === SourceType::User) {
                $report->warn('users', $entry->source_id, sprintf(
                    'No longer exists on the old site, but is still user #%s here. Delete the account in the application, or it can still log in.',
                    self::key($target),
                ));

                continue;
            }

            $found[self::kind($entry, $target)][] = [$entry, $target];
        }

        if ($found === []) {
            return;
        }

        $gone = [];

        foreach (self::KINDS as $kind) {
            $items = $found[$kind] ?? [];

            if ($items !== []) {
                usort($items, fn (array $a, array $b): int => $a[0]->source_id <=> $b[0]->source_id);
                $gone[$kind] = $items;
            }
        }

        $counts = array_map(count(...), $gone);

        if (! $dryRun && $confirm !== null && ! $confirm($counts)) {
            $report->note('Prune: declined, nothing was removed');

            return;
        }

        $report->note($dryRun
            ? 'Prune: content that is gone from the old site would be removed'
            : 'Prune: content that is gone from the old site was removed');

        $progress->start($dryRun ? 'would be removed' : 'removed', array_sum($counts));
        $urls = [];

        foreach ($gone as $kind => $items) {
            foreach ($items as [$entry, $target]) {
                foreach ($this->remove($kind, $entry, $target, $report, $dryRun) as $url) {
                    $urls[$url] = true;
                }

                $progress->advance();
            }
        }

        $progress->finish();

        $report->addRemovedRedirects($this->deleteRedirectsTo($urls));
    }

    /**
     * Whether another row became the same record, as with a mapper that
     * merges rows. The record stays until the last of those rows is gone.
     */
    private function stillMapped(ImportMapEntry $entry): bool
    {
        return ImportMapEntry::query()
            ->where('target_type', $entry->target_type)
            ->where('target_id', $entry->target_id)
            ->whereKeyNot($entry->getKey())
            ->exists();
    }

    /**
     * The entries whose source row the import no longer reads.
     *
     * @return list<ImportMapEntry>
     */
    private function stale(): array
    {
        $stale = [];

        foreach (SourceType::cases() as $type) {
            // While comments are off, the import reads none of them; that says nothing about the old site.
            if ($type === SourceType::Comment && ! $this->config->boolean('wp-migrator.comments.enabled')) {
                continue;
            }

            ImportMapEntry::query()
                ->where('source_type', $type->value)
                ->chunkById(self::CHUNK, function (Collection $entries) use ($type, &$stale): void {
                    /** @var Collection<int, ImportMapEntry> $entries */
                    $ids = array_values(array_map(intval(...), $entries->pluck('source_id')->all()));
                    $read = array_flip($this->read($type, $ids));

                    foreach ($entries as $entry) {
                        if (! isset($read[$entry->source_id])) {
                            $stale[] = $entry;
                        }
                    }
                });
        }

        return $stale;
    }

    /**
     * Which of the source IDs an unrestricted import would read. Attachments,
     * menu items and navigation menus share the IDs of posts, menus those of terms.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function read(SourceType $type, array $ids): array
    {
        return match ($type) {
            SourceType::Post => [
                ...$this->posts->existing($ids, new PostFilter),
                ...$this->media->existing($ids),
                ...$this->menuItems->existing($ids),
                ...$this->posts->existing($ids, NavigationImporter::filter()),
            ],
            SourceType::Term => [
                ...$this->terms->existing($ids, new TermFilter),
                ...$this->menus->existing($ids),
            ],
            SourceType::User => $this->users->existing($ids),
            SourceType::Comment => $this->comments->existing($ids),
        };
    }

    private static function kind(ImportMapEntry $entry, Model $target): string
    {
        return match (true) {
            $target instanceof Comment => 'comments',
            $target instanceof MenuItem => 'menu items',
            $target instanceof Menu => 'menus',
            $target instanceof Post => 'posts',
            $target instanceof Term => 'terms',
            $target instanceof Media => 'media',
            // The model of a custom mapper: named after what it was on the old site.
            $entry->source_type === SourceType::Term => 'terms',
            $entry->source_type === SourceType::Comment => 'comments',
            default => 'posts',
        };
    }

    /**
     * Deletes one record with what belongs to it and returns the new URLs it
     * had, which its redirects pointed to.
     *
     * @return list<string>
     */
    private function remove(string $kind, ImportMapEntry $entry, Model $target, ImportReport $report, bool $dryRun): array
    {
        $what = self::describe($target);
        $files = $target instanceof Media ? self::files($target) : null;

        try {
            $urls = $entry->getConnection()->transaction(function () use ($entry, $target): array {
                $urls = $this->urls($entry, $target);

                $this->deleteWhatBelongsTo($target);
                $this->moveChildrenUp($target);
                $target->delete();
                $entry->delete();

                return $urls;
            });
        } catch (Throwable $exception) {
            $report->failed($kind, $entry->source_id, $exception);

            return [];
        }

        $report->removed($kind, $entry->source_id, $what);

        if ($files !== null && ! $dryRun) {
            try {
                $this->filesystems->disk($files['disk'])->delete($files['paths']);
            } catch (Throwable $exception) {
                $report->warn($kind, $entry->source_id, sprintf('The files of %s could not be deleted: %s', $what, $exception->getMessage()));
            }
        }

        return $urls;
    }

    /**
     * What points to the record without a foreign key that would clean up
     * after it: its SEO data, its terms, its comments and the menu items
     * that link to it. WP hides the menu items of trashed content too.
     */
    private function deleteWhatBelongsTo(Model $target): void
    {
        if ($target instanceof Comment || $target instanceof MenuItem || $target instanceof Menu || $target instanceof Media) {
            return;
        }

        $type = $target->getMorphClass();
        $id = $target->getKey();

        SeoMeta::query()->where('seoable_type', $type)->where('seoable_id', $id)->delete();

        (new Term)->getConnection()->table(Tables::prefixed('termables'))
            ->where('termable_type', $type)
            ->where('termable_id', $id)
            ->delete();

        Comment::query()->where('commentable_type', $type)->where('commentable_id', $id)
            ->chunkById(self::CHUNK, function (Collection $comments): void {
                $ids = $comments->modelKeys();

                ImportMapEntry::query()
                    ->where('target_type', (new Comment)->getMorphClass())
                    ->whereIn('target_id', $ids)
                    ->delete();

                Comment::query()->whereKey($ids)->delete();
            });

        foreach (MenuItem::query()->where('linkable_type', $type)->where('linkable_id', $id)->get() as $item) {
            // Its row stays on the old site, so its entry goes as well: the next import decides afresh.
            ImportMapEntry::query()->where('target_type', $item->getMorphClass())->where('target_id', $item->id)->delete();

            $this->moveChildrenUp($item);
            $item->delete();
        }
    }

    /**
     * Replies and submenu entries would be deleted along with their parent,
     * although the old site still has them; WP moves them up one level too.
     */
    private function moveChildrenUp(Model $target): void
    {
        if ($target instanceof MenuItem || $target instanceof Comment) {
            $target->newQuery()->where('parent_id', $target->getKey())->update(['parent_id' => $target->parent_id]);
        }
    }

    /**
     * The new URLs of the record, taken from the redirects of the query URLs
     * that every post, attachment, category and tag of the old site answered to.
     *
     * @return list<string>
     */
    private function urls(ImportMapEntry $entry, Model $target): array
    {
        $id = $entry->source_id;

        $from = match (true) {
            $entry->source_type === SourceType::Post => ['/?p='.$id, '/?attachment_id='.$id],
            $entry->source_type !== SourceType::Term => [],
            $target instanceof Term && $target->taxonomy === 'post_tag' => ['/?tag='.$target->slug],
            default => ['/?cat='.$id],
        };

        if ($from === []) {
            return [];
        }

        return array_values(array_map(strval(...), Redirect::query()
            ->whereIn('from_path', array_map(RedirectPath::normalise(...), $from))
            ->pluck('to_path')
            ->all()));
    }

    /**
     * Deletes the redirects to URLs that only removed content had. A URL
     * that something on the old site still resolves to keeps its redirects.
     *
     * @param  array<string, true>  $urls
     */
    private function deleteRedirectsTo(array $urls): int
    {
        if ($urls === []) {
            return 0;
        }

        foreach ($this->oldUrls->candidates() as $candidate) {
            if ($candidate->to !== null) {
                unset($urls[$candidate->to]);
            }
        }

        $deleted = 0;

        foreach (array_chunk(array_map(strval(...), array_keys($urls)), self::CHUNK) as $chunk) {
            $deleted += (int) Redirect::query()->whereIn('to_path', $chunk)->delete();
        }

        return $deleted;
    }

    /**
     * The files of a media record, unless another record uses the same original.
     *
     * @return array{disk: string, paths: list<string>}|null
     */
    private static function files(Media $media): ?array
    {
        if ($media->path === '') {
            return null;
        }

        if (Media::query()->where('disk', $media->disk)->where('path', $media->path)->whereKeyNot($media->getKey())->exists()) {
            return null;
        }

        $paths = [$media->path];

        foreach (array_keys($media->variants ?? []) as $size) {
            $path = $media->variantPath($size);

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        return ['disk' => $media->disk, 'paths' => array_values(array_unique($paths))];
    }

    private static function describe(Model $target): string
    {
        return match (true) {
            $target instanceof Post => sprintf('%s "%s"', $target->type, self::short($target->title)),
            $target instanceof Term => sprintf('%s "%s"', $target->taxonomy, self::short($target->name)),
            $target instanceof Media => $target->path !== '' ? $target->path : sprintf('"%s"', self::short((string) $target->title)),
            $target instanceof Menu => sprintf('menu "%s"', self::short($target->name)),
            $target instanceof MenuItem => sprintf('menu item "%s"', self::short($target->title)),
            $target instanceof Comment => (string) $target->author_name === '' ? 'comment' : 'comment by '.self::short((string) $target->author_name),
            default => class_basename($target).' #'.self::key($target),
        };
    }

    private static function short(string $text): string
    {
        return Str::limit($text, 80);
    }

    private static function key(Model $model): string
    {
        $key = $model->getKey();

        return is_scalar($key) ? (string) $key : '?';
    }
}
