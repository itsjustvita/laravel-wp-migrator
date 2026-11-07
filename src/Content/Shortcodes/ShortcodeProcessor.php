<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content\Shortcodes;

/**
 * Finds shortcodes in HTML: [tag attr="x"], [tag /] and [tag]content[/tag].
 *
 * The text is scanned once from left to right. HTML tags, comments and
 * elements that show code are skipped, so brackets in there stay literal.
 * Whether a tag encloses content is decided by looking for its closing tag.
 * Doubled brackets escape a known shortcode. Unknown tags stay where they are.
 */
final class ShortcodeProcessor
{
    /** Elements whose text is not meant to be interpreted. */
    private const array LITERAL_ELEMENTS = ['pre', 'code', 'script', 'style', 'textarea'];

    private const string HTML_TAG = '/\G<(\/?)([a-zA-Z][a-zA-Z0-9:-]*)(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>/';

    private const string OPENING_TAG = '/\G\[([a-zA-Z][a-zA-Z0-9_-]*)(?=[\s\/\]])((?:[^\]\["\']++|"(?:[^"\\\\]|\\\\.)*+"|\'(?:[^\'\\\\]|\\\\.)*+\')*+)\]/s';

    /** The second try for attributes with a stray quote, as in [note it's fine]. */
    private const string OPENING_TAG_LOOSE = '/\G\[([a-zA-Z][a-zA-Z0-9_-]*)(?=[\s\/\]])([^\]\[]*+)\]/';

    private const string ATTRIBUTE = '/\G\s*(?:([a-zA-Z0-9_-]+)\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*+)"|\'((?:[^\'\\\\]|\\\\.)*+)\'|([^\s"\']+))|"((?:[^"\\\\]|\\\\.)*+)"|\'((?:[^\'\\\\]|\\\\.)*+)\'|([^\s"\']+))/s';

    /** @var array<string, true> tags known not to be closed anywhere after the current position */
    private array $unclosed = [];

    /**
     * @param  callable(string): bool  $isKnown
     * @param  callable(Shortcode): string  $render
     * @param  callable(string): void  $onUnknown
     */
    public function process(string $html, callable $isKnown, callable $render, callable $onUnknown): string
    {
        $outer = $this->unclosed;
        $this->unclosed = [];

        try {
            return $this->scan($html, $isKnown, $render, $onUnknown);
        } finally {
            $this->unclosed = $outer;
        }
    }

    /**
     * @param  callable(string): bool  $isKnown
     * @param  callable(Shortcode): string  $render
     * @param  callable(string): void  $onUnknown
     */
    private function scan(string $html, callable $isKnown, callable $render, callable $onUnknown): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($html);

        while ($offset < $length) {
            $next = $offset + strcspn($html, '[<', $offset);

            if ($next >= $length) {
                $output .= substr($html, $offset);

                break;
            }

            $output .= substr($html, $offset, $next - $offset);

            [$text, $offset] = $html[$next] === '<'
                ? $this->skipHtml($html, $next, $isKnown)
                : $this->handleBracket($html, $next, $isKnown, $render, $onUnknown);

            $output .= $text;
        }

        return $output;
    }

    /**
     * @param  callable(string): bool  $isKnown
     * @return array{0: string, 1: int} the text to emit and the offset to continue at
     */
    private function skipHtml(string $html, int $start, callable $isKnown): array
    {
        if (substr($html, $start, 4) === '<!--') {
            $end = strpos($html, '-->', $start + 4);

            return $end === false ? ['<', $start + 1] : [substr($html, $start, $end + 3 - $start), $end + 3];
        }

        if (preg_match(self::HTML_TAG, $html, $matches, 0, $start) !== 1) {
            return ['<', $start + 1];
        }

        $tagEnd = $start + strlen($matches[0]);
        $name = strtolower($matches[2]);

        if ($matches[1] === '/' || ! in_array($name, self::LITERAL_ELEMENTS, true)) {
            return [$matches[0], $tagEnd];
        }

        $close = isset($this->unclosed['<'.$name]) ? false : stripos($html, '</'.$name, $tagEnd);

        if ($close === false) {
            $this->unclosed['<'.$name] = true;

            return [$matches[0], $tagEnd];
        }

        return [$matches[0].$this->unescape(substr($html, $tagEnd, $close - $tagEnd), $isKnown), $close];
    }

    /**
     * @param  callable(string): bool  $isKnown
     * @param  callable(Shortcode): string  $render
     * @param  callable(string): void  $onUnknown
     * @return array{0: string, 1: int}
     */
    private function handleBracket(string $html, int $start, callable $isKnown, callable $render, callable $onUnknown): array
    {
        if (($html[$start + 1] ?? '') === '[') {
            return $this->handleEscaped($html, $start, $isKnown);
        }

        $tag = $this->tagAt($html, $start);

        if ($tag === null) {
            return ['[', $start + 1];
        }

        [$name, $attributeText, $tagEnd] = $tag;

        if (! $isKnown($name)) {
            $onUnknown($name);

            return [substr($html, $start, $tagEnd - $start), $tagEnd];
        }

        $selfClosed = str_ends_with(rtrim($attributeText), '/');
        $attrs = $this->parseAttributes($selfClosed ? rtrim(rtrim($attributeText), '/') : $attributeText);
        $close = $selfClosed ? false : $this->findClosingTag($html, $name, $tagEnd);

        if ($close === false) {
            return [$render(new Shortcode($name, $attrs)), $tagEnd];
        }

        return [
            $render(new Shortcode($name, $attrs, substr($html, $tagEnd, $close - $tagEnd))),
            $close + strlen('[/'.$name.']'),
        ];
    }

    /**
     * @param  callable(string): bool  $isKnown
     * @return array{0: string, 1: int}
     */
    private function handleEscaped(string $html, int $start, callable $isKnown): array
    {
        $tag = $this->tagAt($html, $start + 1);

        if ($tag === null) {
            return ['[', $start + 1];
        }

        [$name, , $tagEnd] = $tag;

        if (($html[$tagEnd] ?? '') === ']') {
            $inner = substr($html, $start + 1, $tagEnd - $start - 1);

            return [$isKnown($name) ? $inner : '['.$inner.']', $tagEnd + 1];
        }

        if (! $isKnown($name)) {
            return [substr($html, $start, $tagEnd - $start), $tagEnd];
        }

        $close = strpos($html, '[/'.$name.']]', $tagEnd);

        if ($close === false) {
            return [substr($html, $start, $tagEnd - $start), $tagEnd];
        }

        $end = $close + strlen('[/'.$name.']');

        return [substr($html, $start + 1, $end - $start - 1), $end + 1];
    }

    /**
     * @return array{0: string, 1: string, 2: int}|null tag name, attribute text and the offset after the tag
     */
    private function tagAt(string $html, int $start): ?array
    {
        if (preg_match(self::OPENING_TAG, $html, $matches, 0, $start) !== 1
            && preg_match(self::OPENING_TAG_LOOSE, $html, $matches, 0, $start) !== 1) {
            return null;
        }

        return [$matches[1], $matches[2], $start + strlen($matches[0])];
    }

    private function findClosingTag(string $html, string $name, int $from): int|false
    {
        if (isset($this->unclosed[$name])) {
            return false;
        }

        $closing = '[/'.$name.']';
        $search = $from;

        while (($position = strpos($html, $closing, $search)) !== false) {
            if (! $this->isInsideHtmlTag($html, $from, $position)) {
                return $position;
            }

            $search = $position + 1;
        }

        $this->unclosed[$name] = true;

        return false;
    }

    private function isInsideHtmlTag(string $html, int $from, int $position): bool
    {
        $before = substr($html, $from, $position - $from);
        $lastOpen = strrpos($before, '<');

        if ($lastOpen === false) {
            return false;
        }

        $tagStart = $from + $lastOpen;

        if (preg_match(self::HTML_TAG, $html, $matches, 0, $tagStart) !== 1) {
            return false;
        }

        return $tagStart + strlen($matches[0]) > $position;
    }

    /**
     * @return array<int|string, string>
     */
    private function parseAttributes(string $text): array
    {
        $attrs = [];
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length && preg_match(self::ATTRIBUTE, $text, $matches, PREG_UNMATCHED_AS_NULL, $offset) === 1) {
            $offset += strlen((string) $matches[0]);

            if ($matches[1] !== null) {
                $attrs[strtolower($matches[1])] = $this->unslash($matches[2] ?? $matches[3] ?? $matches[4] ?? '');
            } else {
                $attrs[] = $this->unslash($matches[5] ?? $matches[6] ?? $matches[7] ?? '');
            }
        }

        return $attrs;
    }

    private function unslash(string $value): string
    {
        return str_replace(['\\"', "\\'", '\\\\'], ['"', "'", '\\'], $value);
    }

    /**
     * @param  callable(string): bool  $isKnown
     */
    private function unescape(string $text, callable $isKnown): string
    {
        if (! str_contains($text, '[[')) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/\[(\[([a-zA-Z][a-zA-Z0-9_-]*)(?=[\s\/\]])[^\]\[]*+\])\]/',
            fn (array $matches): string => $isKnown($matches[2]) ? $matches[1] : $matches[0],
            $text,
        );
    }
}
