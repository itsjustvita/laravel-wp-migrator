<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Seo;

enum SeoPlugin: string
{
    case Yoast = 'yoast';
    case RankMath = 'rank-math';

    /**
     * The pattern of a template variable: %%title%% for Yoast, %title% for Rank Math.
     */
    public function variablePattern(): string
    {
        return $this === self::Yoast ? '/%%([a-z0-9_]+)%%/i' : '/%([a-z0-9_]+)%/i';
    }
}
