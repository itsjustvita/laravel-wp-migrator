<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Term;
use ItsJustVita\WpMigrator\Source\Dto\SourceTerm;

class DefaultTermMapper implements TermMapper
{
    public function map(SourceTerm $term, ImportContext $context): ?Model
    {
        $model = $context->existing(SourceType::Term, $term->id, Term::class) ?? new Term;

        $model->fill([
            'taxonomy' => $term->taxonomy,
            'name' => $term->name,
            'slug' => $term->slug !== '' ? $term->slug : Str::slug($term->name),
            'description' => $term->description !== '' ? $term->description : null,
            'parent_id' => $context->resolve(SourceType::Term, $term->parentId, $term->id, 'parent term', Term::class),
        ]);

        $model->save();

        return $model;
    }
}
