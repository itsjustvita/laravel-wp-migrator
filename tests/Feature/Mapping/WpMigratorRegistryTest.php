<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use ItsJustVita\WpMigrator\Facades\WpMigrator;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Mapping\DefaultMediaMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultPostMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultTermMapper;
use ItsJustVita\WpMigrator\Mapping\DefaultUserMapper;
use ItsJustVita\WpMigrator\Mapping\PostMapper;
use ItsJustVita\WpMigrator\Mapping\TermMapper;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Source\Dto\SourceTerm;

final class EventMapper implements PostMapper
{
    public function map(SourcePost $post, ImportContext $context): ?Model
    {
        return null;
    }
}

final class GenreMapper implements TermMapper
{
    public function map(SourceTerm $term, ImportContext $context): ?Model
    {
        return null;
    }
}

it('falls back to the default mappers', function () {
    expect(WpMigrator::postMapper('post'))->toBeInstanceOf(DefaultPostMapper::class)
        ->and(WpMigrator::termMapper('category'))->toBeInstanceOf(DefaultTermMapper::class)
        ->and(WpMigrator::userMapper())->toBeInstanceOf(DefaultUserMapper::class)
        ->and(WpMigrator::mediaMapper())->toBeInstanceOf(DefaultMediaMapper::class);
});

it('registers mappers per post type and taxonomy', function () {
    WpMigrator::mapPostType('event', EventMapper::class);
    WpMigrator::mapTaxonomy('genre', GenreMapper::class);

    expect(WpMigrator::postMapper('event'))->toBeInstanceOf(EventMapper::class)
        ->and(WpMigrator::postMapper('post'))->toBeInstanceOf(DefaultPostMapper::class)
        ->and(WpMigrator::termMapper('genre'))->toBeInstanceOf(GenreMapper::class)
        ->and(WpMigrator::termMapper('category'))->toBeInstanceOf(DefaultTermMapper::class);
});

it('rejects classes that do not implement the mapper contract', function () {
    WpMigrator::mapPostType('event', GenreMapper::class);
})->throws(InvalidArgumentException::class);
