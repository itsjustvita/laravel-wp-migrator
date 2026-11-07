<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Fields;

use Illuminate\Config\Repository;
use Illuminate\Support\Str;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\Dto\SourcePost;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\Serialized;

/**
 * Builds posts.fields from post meta: ACF fields converted to typed values,
 * the remaining meta copied as it is, in the order of the meta rows.
 *
 * Keys starting with "_" are internal and only copied when allow-listed.
 */
class FieldResolver
{
    public function __construct(
        private readonly Repository $config,
        private readonly AcfConverter $acf,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(SourcePost $post, ImportContext $context): array
    {
        if (! $this->config->boolean('wp-migrator.fields.acf', true)) {
            return $this->plain($post->meta, $post->id, $context);
        }

        ['fields' => $acf, 'consumed' => $consumed] = $this->acf->convert($post->meta, $post->id, $context);

        return $this->plain($post->meta, $post->id, $context, $acf, $consumed);
    }

    /**
     * The meta as fields, without looking at ACF. Needs no source database.
     *
     * @param  array<string, mixed>  $converted  values to use instead of the raw meta
     * @param  array<string, true>  $consumed  keys to leave out
     * @return array<string, mixed>
     */
    public function plain(MetaBag $meta, int $postId, ImportContext $context, array $converted = [], array $consumed = []): array
    {
        $allowed = $this->config->array('wp-migrator.fields.allowed_internal_keys');
        $excluded = $this->config->array('wp-migrator.fields.excluded_keys');

        $fields = [];

        foreach ($meta->toArray() as $key => $values) {
            if (array_key_exists($key, $converted)) {
                $fields[$key] = $converted[$key];

                continue;
            }

            if (isset($consumed[$key])
                || (str_starts_with($key, '_') && ! Str::is($allowed, $key))
                || Str::is($excluded, $key)) {
                continue;
            }

            $decoded = array_map(fn (string $value): mixed => $this->decode($key, $value, $postId, $context), $values);

            $fields[$key] = count($decoded) === 1 ? $decoded[0] : $decoded;
        }

        return $fields;
    }

    private function decode(string $key, string $value, int $postId, ImportContext $context): mixed
    {
        try {
            return Serialized::decode($value);
        } catch (MalformedSerializedValue) {
            $context->warn($postId, sprintf('Custom field "%s" holds a malformed serialized value; it was kept as a string.', $key));

            return $value;
        }
    }
}
