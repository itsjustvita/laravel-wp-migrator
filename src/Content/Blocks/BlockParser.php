<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Blocks;

/**
 * Parses the block comment format:
 *
 *     <!-- wp:name {"json":"attrs"} -->inner<!-- /wp:name -->
 *     <!-- wp:namespace/name {"json":"attrs"} /-->
 *
 * The parser never fails. A comment that is not a well-formed marker stays
 * plain HTML, and a marker that is never matched by its counterpart is
 * dissolved back into the HTML around it, so no content gets lost.
 */
final class BlockParser
{
    /**
     * Deeper markers stay plain HTML. Every block level takes two levels in
     * the stored JSON, and MySQL refuses JSON documents nested deeper than 100.
     */
    public const int MAX_DEPTH = 32;

    /** How many comment terminators are tried for attributes that contain "-->". */
    private const int MAX_TERMINATOR_ATTEMPTS = 32;

    private const string CORE_NAMESPACE = 'core/';

    private const string MARKER_START = '/\G<!--\s+(\/?)wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)\s+/';

    /** @var list<int>|null offsets of every "-->" in the document, collected on first use */
    private ?array $terminators = null;

    /**
     * @return list<Block>
     */
    public function parse(string $content): array
    {
        $this->terminators = null;

        $document = new OpenBlock;
        /** @var list<OpenBlock> $open */
        $open = [];
        $offset = 0;

        while (($start = strpos($content, '<!--', $offset)) !== false) {
            $current = $open === [] ? $document : $open[array_key_last($open)];
            $marker = $this->markerAt($content, $start);

            if ($marker === null) {
                $current->append(substr($content, $offset, $start + 4 - $offset));
                $offset = $start + 4;

                continue;
            }

            if ($start > $offset) {
                $current->append(substr($content, $offset, $start - $offset));
            }

            $text = substr($content, $start, $marker['end'] - $start);
            $offset = $marker['end'];

            if ($marker['type'] === 'void') {
                $current->append(new Block($marker['name'], $marker['attrs']));
            } elseif ($marker['type'] === 'closer') {
                $open = $this->close($open, $document, $marker['name'], $text);
            } elseif (count($open) >= self::MAX_DEPTH) {
                $current->append($text);
            } else {
                $open[] = new OpenBlock($marker['name'], $marker['attrs'], $text);
            }
        }

        if ($offset < strlen($content)) {
            ($open === [] ? $document : $open[array_key_last($open)])->append(substr($content, $offset));
        }

        while ($open !== []) {
            $open = $this->dissolveInnermost($open, $document);
        }

        $blocks = [];

        foreach ($document->parts() as $part) {
            if ($part instanceof Block) {
                $blocks[] = $part;
            } elseif (trim($part) !== '') {
                $blocks[] = Block::freeform(trim($part));
            }
        }

        return $blocks;
    }

    /**
     * @return array{type: 'opener'|'closer'|'void', name: string, attrs: array<array-key, mixed>, end: int}|null
     */
    private function markerAt(string $content, int $start): ?array
    {
        if (preg_match(self::MARKER_START, $content, $matches, 0, $start) !== 1) {
            return null;
        }

        $closer = $matches[1] === '/';
        $name = str_contains($matches[2], '/') ? $matches[2] : self::CORE_NAMESPACE.$matches[2];
        $cursor = $start + strlen($matches[0]);

        if (substr($content, $cursor, 3) === '-->') {
            return ['type' => $closer ? 'closer' : 'opener', 'name' => $name, 'attrs' => [], 'end' => $cursor + 3];
        }

        if ($closer) {
            return null;
        }

        if (substr($content, $cursor, 4) === '/-->') {
            return ['type' => 'void', 'name' => $name, 'attrs' => [], 'end' => $cursor + 4];
        }

        if (($content[$cursor] ?? '') !== '{') {
            return null;
        }

        return $this->attributesMarker($content, $cursor, $name);
    }

    /**
     * Finds the terminator that ends the attributes. JSON strings may contain
     * "-->", so a terminator only counts when the text before it is valid JSON.
     *
     * @return array{type: 'opener'|'void', name: string, attrs: array<array-key, mixed>, end: int}|null
     */
    private function attributesMarker(string $content, int $cursor, string $name): ?array
    {
        // Attributes end before the next comment opener. WP escapes "<" in
        // serialized attributes, so valid ones never contain it, and the
        // search areas of different markers cannot overlap. That keeps the
        // total work linear in the length of the document.
        $limit = strpos($content, '<!--', $cursor);
        $limit = $limit === false ? strlen($content) : $limit;
        $search = $cursor;

        for ($attempt = 0; $attempt < self::MAX_TERMINATOR_ATTEMPTS; $attempt++) {
            $end = $this->nextTerminator($content, $search);

            if ($end === false || $end >= $limit) {
                return null;
            }

            $search = $end + 3;
            $jsonEnd = $end;
            $void = ($content[$end - 1] ?? '') === '/';

            if ($void) {
                $jsonEnd--;
            }

            $beforeWhitespace = $jsonEnd;

            while ($jsonEnd > $cursor && strpos(" \t\r\n", $content[$jsonEnd - 1]) !== false) {
                $jsonEnd--;
            }

            if ($jsonEnd === $beforeWhitespace || $content[$jsonEnd - 1] !== '}') {
                continue;
            }

            $attrs = json_decode(substr($content, $cursor, $jsonEnd - $cursor), true);

            if (is_array($attrs)) {
                return ['type' => $void ? 'void' : 'opener', 'name' => $name, 'attrs' => $attrs, 'end' => $end + 3];
            }
        }

        return null;
    }

    /**
     * The first "-->" at or after the offset. Every marker with attributes
     * asks this at least once, so the offsets are collected only once and then
     * searched. That keeps a document full of broken markers from being
     * scanned over and over.
     */
    private function nextTerminator(string $content, int $offset): int|false
    {
        $terminators = $this->terminators ??= $this->collectTerminators($content);

        $low = 0;
        $high = count($terminators);

        while ($low < $high) {
            $middle = ($low + $high) >> 1;

            if ($terminators[$middle] < $offset) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $terminators[$low] ?? false;
    }

    /**
     * @return list<int>
     */
    private function collectTerminators(string $content): array
    {
        $terminators = [];
        $offset = 0;

        while (($position = strpos($content, '-->', $offset)) !== false) {
            $terminators[] = $position;
            $offset = $position + 3;
        }

        return $terminators;
    }

    /**
     * Closes the innermost open block of that name. Blocks opened inside it
     * were never closed and dissolve. Without such a block, the closer is text.
     *
     * @param  list<OpenBlock>  $open
     * @return list<OpenBlock>
     */
    private function close(array $open, OpenBlock $document, string $name, string $marker): array
    {
        $index = null;

        for ($i = count($open) - 1; $i >= 0; $i--) {
            if ($open[$i]->name === $name) {
                $index = $i;

                break;
            }
        }

        if ($index === null) {
            ($open === [] ? $document : $open[array_key_last($open)])->append($marker);

            return $open;
        }

        while (count($open) - 1 > $index) {
            $open = $this->dissolveInnermost($open, $document);
        }

        $block = $open[$index]->toBlock();
        unset($open[$index]);
        $open = array_values($open);

        ($open === [] ? $document : $open[array_key_last($open)])->append($block);

        return $open;
    }

    /**
     * A block that was never closed turns back into text: its marker and
     * everything it collected move up to the level around it.
     *
     * @param  list<OpenBlock>  $open
     * @return list<OpenBlock>
     */
    private function dissolveInnermost(array $open, OpenBlock $document): array
    {
        $last = array_key_last($open);

        if ($last === null) {
            return $open;
        }

        $block = $open[$last];
        unset($open[$last]);
        $open = array_values($open);

        $target = $open === [] ? $document : $open[array_key_last($open)];
        $target->append($block->marker);

        foreach ($block->parts() as $part) {
            $target->append($part);
        }

        return $open;
    }
}
