<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Fields;

use Carbon\CarbonImmutable;
use ItsJustVita\WpMigrator\Content\ContentTransformer;
use ItsJustVita\WpMigrator\Content\Urls\UrlRewriter;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Models\Media;
use ItsJustVita\WpMigrator\Source\Dto\MetaBag;
use ItsJustVita\WpMigrator\Source\WpDatabase;
use ItsJustVita\WpMigrator\Support\MalformedSerializedValue;
use ItsJustVita\WpMigrator\Support\Serialized;
use Throwable;

/**
 * Converts the post meta of ACF fields into typed values.
 *
 * ACF stores a value under the field name and the field key under the name
 * with a leading underscore. Repeaters store their row count and each sub
 * value as "{name}_{row}_{sub}", flexible content the list of its layout
 * names, groups each sub value as "{name}_{sub}".
 */
class AcfConverter
{
    public function __construct(
        private readonly AcfDefinitions $definitions,
        private readonly UrlRewriter $urls,
        private readonly ContentTransformer $content,
        private readonly WpDatabase $db,
    ) {}

    /**
     * @return array{fields: array<string, mixed>, consumed: array<string, true>}
     */
    public function convert(MetaBag $meta, int $postId, ImportContext $context): array
    {
        $state = new AcfConversion($meta, $postId, $context, count($meta->keys()));

        foreach ($meta->keys() as $key) {
            if (str_starts_with($key, '_')) {
                continue;
            }

            $reference = $meta->get('_'.$key);
            $field = $reference === null ? null : $this->definitions->field($reference);

            if ($field === null || ! $field->isTopLevel) {
                continue;
            }

            $state->fields[$key] = $this->value($field, $key, $state);
        }

        return ['fields' => $state->fields, 'consumed' => $state->consumed];
    }

    private function value(AcfField $field, string $metaKey, AcfConversion $state): mixed
    {
        $state->consumed[$metaKey] = true;
        $state->consumed['_'.$metaKey] = true;

        $raw = $state->meta->get($metaKey);

        return match ($field->type) {
            'repeater' => $this->repeater($field, $metaKey, $raw, $state),
            'flexible_content' => $this->flexibleContent($field, $metaKey, $state),
            'group' => $this->group($field, $metaKey, $state),
            'true_false' => $raw === '1',
            'number', 'range' => $this->number($raw),
            'date_picker' => $this->date($field, $raw, $state),
            'date_time_picker' => $this->dateTime($field, $raw, $state),
            'image', 'file' => $this->media($raw, $state),
            'gallery' => $this->list($this->decode($field, $raw, $state), fn (mixed $id): ?int => $this->media($id, $state)),
            'post_object', 'relationship', 'page_link' => $this->posts($field, $raw, $state),
            'user' => $this->references($field, $raw, SourceType::User, 'acf user', $state),
            'taxonomy' => $this->references($field, $raw, SourceType::Term, 'acf term', $state),
            'checkbox' => $this->asList($this->decode($field, $raw, $state)),
            'url', 'oembed' => $raw === null ? null : $this->urls->rewrite($raw, $state->postId, $state->context),
            'link' => $this->link($field, $raw, $state),
            'wysiwyg' => $raw === null ? null : $this->content->formatHtml($raw, $state->postId, $state->context),
            'text', 'textarea', 'email', 'password', 'color_picker', 'time_picker' => $raw,
            default => $this->decode($field, $raw, $state),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function repeater(AcfField $field, string $metaKey, ?string $raw, AcfConversion $state): array
    {
        $rows = [];
        $count = min($this->count($raw), $state->rowLimit);

        for ($row = 0; $row < $count; $row++) {
            $values = [];

            foreach ($this->definitions->children($field) as $sub) {
                $values[$sub->name] = $this->value($sub, $metaKey.'_'.$row.'_'.$sub->name, $state);
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function flexibleContent(AcfField $field, string $metaKey, AcfConversion $state): array
    {
        $layouts = $this->asList($this->decode($field, $state->meta->get($metaKey), $state));
        $rows = [];

        foreach (array_slice($layouts, 0, $state->rowLimit) as $row => $layout) {
            $name = is_scalar($layout) ? (string) $layout : '';
            $layoutKey = $field->layoutKey($name);
            $values = ['layout' => $name];

            foreach ($this->definitions->children($field) as $sub) {
                if ($layoutKey !== null && $sub->parentLayout() === $layoutKey) {
                    $values[$sub->name] = $this->value($sub, $metaKey.'_'.$row.'_'.$sub->name, $state);
                }
            }

            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function group(AcfField $field, string $metaKey, AcfConversion $state): array
    {
        $values = [];

        foreach ($this->definitions->children($field) as $sub) {
            $values[$sub->name] = $this->value($sub, $metaKey.'_'.$sub->name, $state);
        }

        return $values;
    }

    private function count(?string $raw): int
    {
        return $raw !== null && ctype_digit($raw) ? (int) min($raw, PHP_INT_MAX) : 0;
    }

    private function number(?string $raw): int|float|null
    {
        if ($raw === null || ! is_numeric($raw)) {
            return null;
        }

        return preg_match('/^-?\d+$/', $raw) === 1 ? (int) $raw : (float) $raw;
    }

    private function date(AcfField $field, ?string $raw, AcfConversion $state): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $matches) === 1) {
            return $matches[1].'-'.$matches[2].'-'.$matches[3];
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $raw;
        }

        $state->context->warn($state->postId, sprintf('Date field "%s" holds "%s", which is not a date; it was kept as it is.', $field->name, $raw));

        return $raw;
    }

    /**
     * ACF stores the wall clock time of the site, so it is read in the site's timezone.
     */
    private function dateTime(AcfField $field, ?string $raw, AcfConversion $state): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw, $this->db->timezone())->toIso8601String();
        } catch (Throwable) {
            $state->context->warn($state->postId, sprintf('Date field "%s" holds "%s", which is not a date; it was kept as it is.', $field->name, $raw));

            return $raw;
        }
    }

    private function media(mixed $raw, AcfConversion $state): ?int
    {
        if (! is_scalar($raw) || ! ctype_digit((string) $raw) || (int) $raw === 0) {
            return null;
        }

        return $state->context->resolve(SourceType::Post, (int) $raw, $state->postId, 'acf media', Media::class);
    }

    /**
     * Post references hold IDs. A page link can also hold a URL, such as one to an archive.
     */
    private function posts(AcfField $field, ?string $raw, AcfConversion $state): mixed
    {
        $value = $this->decode($field, $raw, $state);

        if (is_string($value) && $value !== '' && ! ctype_digit($value)) {
            return $this->urls->rewrite($value, $state->postId, $state->context);
        }

        if ($field->type === 'relationship') {
            $value = $this->asList($value);
        }

        return $this->resolveIds($value, SourceType::Post, 'acf post', $state);
    }

    private function references(AcfField $field, ?string $raw, SourceType $type, string $kind, AcfConversion $state): mixed
    {
        return $this->resolveIds($this->decode($field, $raw, $state), $type, $kind, $state);
    }

    /**
     * One ID or a list of IDs, each replaced by the ID of its target. A list
     * loses the IDs that cannot be resolved; they are reported.
     */
    private function resolveIds(mixed $value, SourceType $type, string $kind, AcfConversion $state): mixed
    {
        $resolve = function (mixed $id) use ($type, $kind, $state): ?int {
            if (! is_scalar($id) || ! ctype_digit((string) $id) || (int) $id === 0) {
                return null;
            }

            return $state->context->resolve($type, (int) $id, $state->postId, $kind);
        };

        if (is_array($value)) {
            return $this->list($value, $resolve);
        }

        return $resolve($value);
    }

    /**
     * @param  callable(mixed): mixed  $map
     * @return list<mixed>
     */
    private function list(mixed $values, callable $map): array
    {
        $list = [];

        foreach ($this->asList($values) as $value) {
            $mapped = $map($value);

            if ($mapped !== null) {
                $list[] = $mapped;
            }
        }

        return $list;
    }

    private function link(AcfField $field, ?string $raw, AcfConversion $state): mixed
    {
        $value = $this->decode($field, $raw, $state);

        if (is_array($value) && is_string($value['url'] ?? null)) {
            $value['url'] = $this->urls->rewrite($value['url'], $state->postId, $state->context);
        } elseif (is_string($value)) {
            $value = $this->urls->rewrite($value, $state->postId, $state->context);
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function asList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return is_array($value) ? array_values($value) : [$value];
    }

    private function decode(AcfField $field, ?string $raw, AcfConversion $state): mixed
    {
        if ($raw === null) {
            return null;
        }

        try {
            return Serialized::decode($raw);
        } catch (MalformedSerializedValue) {
            $state->context->warn($state->postId, sprintf('Field "%s" holds a malformed serialized value; it was kept as a string.', $field->name));

            return $raw;
        }
    }
}
