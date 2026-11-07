<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Shortcodes\Handlers;

use ItsJustVita\WpMigrator\Content\Shortcodes\Shortcode;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeContext;
use ItsJustVita\WpMigrator\Content\Shortcodes\ShortcodeHandler;
use ItsJustVita\WpMigrator\Content\Urls\MediaUrls;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Source\WpDatabase;

/**
 * [gallery ids="1,2,3" columns="3" size="medium" link="file"]. Without a list
 * of IDs, a gallery shows the images attached to its post.
 */
final class GalleryHandler implements ShortcodeHandler
{
    private const int DEFAULT_COLUMNS = 3;

    public function __construct(
        private readonly MediaUrls $urls,
        private readonly WpDatabase $db,
    ) {}

    public function render(Shortcode $shortcode, ShortcodeContext $context): string
    {
        $items = '';

        foreach ($this->sourceIds($shortcode, $context) as $id) {
            $media = $context->media($id, 'gallery image');

            if ($media !== null) {
                $items .= $this->item($media, $shortcode);
            }
        }

        if ($items === '') {
            return '';
        }

        $columns = $shortcode->attr('columns', '') ?? '';
        $columns = ctype_digit($columns) ? (int) $columns : self::DEFAULT_COLUMNS;

        return '<figure class="wp-block-gallery wp-gallery columns-'.$columns.'">'.$items.'</figure>';
    }

    /**
     * @return list<int>
     */
    private function sourceIds(Shortcode $shortcode, ShortcodeContext $context): array
    {
        $list = $shortcode->attr('ids') ?? $shortcode->attr('include');

        if ($list === null) {
            return $this->attachedImages($context->postId);
        }

        $ids = [];

        foreach (preg_split('/[\s,]+/', $list, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $id) {
            if (ctype_digit($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function attachedImages(int $postId): array
    {
        $ids = $this->db->table('posts')
            ->where('post_type', 'attachment')
            ->where('post_parent', $postId)
            ->where('post_mime_type', 'like', 'image/%')
            ->orderBy('menu_order')
            ->orderBy('ID')
            ->pluck('ID');

        return array_values(array_map(intval(...), $ids->all()));
    }

    private function item(Media $media, Shortcode $shortcode): string
    {
        $src = e($this->urls->of($media, $shortcode->attr('size')));
        $image = '<img src="'.$src.'" alt="'.e($media->alt ?? '').'" loading="lazy" />';

        if ($shortcode->attr('link') === 'file') {
            $image = '<a href="'.e($this->urls->of($media)).'">'.$image.'</a>';
        }

        $caption = $media->caption !== null && $media->caption !== '' ? '<figcaption>'.$media->caption.'</figcaption>' : '';

        return '<figure class="wp-block-image">'.$image.$caption.'</figure>';
    }
}
