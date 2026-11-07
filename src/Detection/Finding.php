<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection;

/**
 * The result of one detector run. Content findings carry a post count and a
 * few sample IDs; structural findings (a plugin table exists) carry a note.
 */
final readonly class Finding
{
    /**
     * @param  list<int>  $sampleIds
     */
    public function __construct(
        public string $key,
        public string $label,
        public bool $detected,
        public int $count = 0,
        public array $sampleIds = [],
        public ?string $note = null,
    ) {}

    public static function none(string $key, string $label): self
    {
        return new self($key, $label, false);
    }

    /**
     * @param  list<int>  $sampleIds
     */
    public static function posts(string $key, string $label, int $count, array $sampleIds, ?string $note = null): self
    {
        return new self($key, $label, $count > 0 || $note !== null, $count, $sampleIds, $note);
    }

    public static function present(string $key, string $label, string $note): self
    {
        return new self($key, $label, true, 0, [], $note);
    }

    /**
     * "3 posts (IDs 1, 2, 3); Order tables found: wp_wc_orders"
     */
    public function summary(): string
    {
        $parts = [];

        if ($this->count > 0) {
            $parts[] = sprintf(
                '%d %s (%s %s%s)',
                $this->count,
                $this->count === 1 ? 'post' : 'posts',
                count($this->sampleIds) === 1 ? 'ID' : 'IDs',
                implode(', ', $this->sampleIds),
                $this->count > count($this->sampleIds) ? ', …' : '',
            );
        }

        if ($this->note !== null) {
            $parts[] = $this->note;
        }

        return implode('; ', $parts);
    }
}
