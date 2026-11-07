<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Fields;

use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;

/**
 * The state of converting the fields of one post.
 *
 * @internal
 */
final class AcfConversion
{
    /** @var array<string, mixed> */
    public array $fields = [];

    /** @var array<string, true> meta keys that belong to a converted field */
    public array $consumed = [];

    /**
     * @param  int  $rowLimit  rows need meta keys, so no list can have more rows than there are keys
     */
    public function __construct(
        public readonly MetaBag $meta,
        public readonly int $postId,
        public readonly ImportContext $context,
        public readonly int $rowLimit,
    ) {}
}
