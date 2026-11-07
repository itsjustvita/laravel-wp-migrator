<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content;

/**
 * Turns classic editor content into paragraphs: blank lines separate
 * paragraphs, single line breaks become <br />, and block-level elements are
 * not wrapped. Verbatim elements such as <pre> keep their content untouched.
 *
 * The content is split into tokens first and block elements are matched up
 * with one pass over them, so malformed markup costs no extra time and never
 * fails. An element without a counterpart stays in place as a standalone tag.
 */
final class Autop
{
    /** Elements that hold flowing content: loose text in them gets paragraphs when needed. */
    private const array FLOW = ['div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav', 'details', 'form', 'fieldset', 'hgroup', 'center', 'blockquote'];

    /** Elements that only hold other elements; whitespace between them means nothing. */
    private const array STRUCTURE = ['table', 'thead', 'tbody', 'tfoot', 'tr', 'colgroup', 'ul', 'ol', 'dl', 'menu', 'dir'];

    /** Elements that usually hold one piece of text, and paragraphs only after a blank line. */
    private const array CELL = ['li', 'td', 'th', 'dt', 'dd', 'caption', 'figure', 'figcaption', 'summary', 'legend', 'address'];

    /** Elements that hold text only. */
    private const array TEXT = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    /** Block-level elements without content. */
    private const array VOID_BLOCK = ['hr', 'col', 'area'];

    /** Block-level elements whose content must not change. */
    private const array VERBATIM_BLOCK = ['pre', 'script', 'style', 'math'];

    /** Inline elements whose content must not change. */
    private const array VERBATIM_INLINE = ['textarea', 'select', 'svg', 'iframe', 'video', 'audio', 'object', 'canvas'];

    private const array VOID_INLINE = ['br', 'img', 'input', 'embed', 'wbr', 'param', 'source', 'track', 'link', 'meta', 'base'];

    /** Deeper elements stay as they are; real content never nests this far. */
    private const int MAX_DEPTH = 128;

    private const string TAG = '/\G<(\/?)([a-zA-Z][a-zA-Z0-9:-]*)(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>/';

    private const string BLANK_LINE = '/\n[ \t]*\n(?:[ \t]*\n)*/';

    /** @var list<array{type: 'text'|'open'|'close'|'void'|'verbatim'|'comment', raw: string, name: string, block: bool}> */
    private array $tokens = [];

    /** @var array<int, int> index of an opening tag => index of its closing tag */
    private array $closers = [];

    public function format(string $html): string
    {
        $html = str_replace(["\r\n", "\r"], "\n", $html);

        if (trim($html) === '') {
            return '';
        }

        $this->tokens = $this->tokenize($html);
        $this->closers = $this->matchBlocks();

        return implode("\n", $this->formatFlow(0, count($this->tokens), true, 0));
    }

    /**
     * @return list<array{type: 'text'|'open'|'close'|'void'|'verbatim'|'comment', raw: string, name: string, block: bool}>
     */
    private function tokenize(string $html): array
    {
        $tokens = [];
        $textStart = 0;
        $offset = 0;
        $length = strlen($html);

        while ($offset < $length && ($start = strpos($html, '<', $offset)) !== false) {
            $token = $this->tokenAt($html, $start);

            if ($token === null) {
                $offset = $start + 1;

                continue;
            }

            if ($start > $textStart) {
                $tokens[] = ['type' => 'text', 'raw' => substr($html, $textStart, $start - $textStart), 'name' => '', 'block' => false];
            }

            $tokens[] = $token;
            $offset = $textStart = $start + strlen($token['raw']);
        }

        if ($textStart < $length) {
            $tokens[] = ['type' => 'text', 'raw' => substr($html, $textStart), 'name' => '', 'block' => false];
        }

        return $tokens;
    }

    /**
     * @return array{type: 'open'|'close'|'void'|'verbatim'|'comment', raw: string, name: string, block: bool}|null
     */
    private function tokenAt(string $html, int $start): ?array
    {
        if (substr($html, $start, 4) === '<!--') {
            $end = strpos($html, '-->', $start + 4);

            return $end === false ? null : ['type' => 'comment', 'raw' => substr($html, $start, $end + 3 - $start), 'name' => '', 'block' => false];
        }

        if (preg_match(self::TAG, $html, $matches, 0, $start) !== 1) {
            return null;
        }

        $raw = $matches[0];
        $name = strtolower($matches[2]);
        $block = $this->isBlock($name);

        if ($matches[1] === '/') {
            return ['type' => 'close', 'raw' => $raw, 'name' => $name, 'block' => $block];
        }

        if (in_array($name, self::VERBATIM_BLOCK, true) || in_array($name, self::VERBATIM_INLINE, true)) {
            $close = stripos($html, '</'.$name, $start + strlen($raw));
            $end = $close === false ? false : strpos($html, '>', $close);

            return $end === false
                ? ['type' => 'void', 'raw' => $raw, 'name' => $name, 'block' => $block]
                : ['type' => 'verbatim', 'raw' => substr($html, $start, $end + 1 - $start), 'name' => $name, 'block' => $block];
        }

        if (str_ends_with($raw, '/>') || in_array($name, self::VOID_BLOCK, true) || in_array($name, self::VOID_INLINE, true)) {
            return ['type' => 'void', 'raw' => $raw, 'name' => $name, 'block' => $block];
        }

        return ['type' => 'open', 'raw' => $raw, 'name' => $name, 'block' => $block];
    }

    private function isBlock(string $name): bool
    {
        return in_array($name, self::FLOW, true)
            || in_array($name, self::STRUCTURE, true)
            || in_array($name, self::CELL, true)
            || in_array($name, self::TEXT, true)
            || in_array($name, self::VOID_BLOCK, true)
            || in_array($name, self::VERBATIM_BLOCK, true);
    }

    /**
     * @return array<int, int>
     */
    private function matchBlocks(): array
    {
        $stacks = [];
        $closers = [];

        foreach ($this->tokens as $index => $token) {
            if (! $token['block']) {
                continue;
            }

            if ($token['type'] === 'open') {
                $stacks[$token['name']][] = $index;
            } elseif ($token['type'] === 'close' && ($stacks[$token['name']] ?? []) !== []) {
                $closers[(int) array_pop($stacks[$token['name']])] = $index;
            }
        }

        return $closers;
    }

    /**
     * Formats the tokens from $from up to $to as a sequence of blocks and
     * returns one string per block.
     *
     * @return list<string>
     */
    private function formatFlow(int $from, int $to, bool $wrap, int $depth): array
    {
        $parts = [];
        $runStart = $from;
        $i = $from;

        while ($i < $to) {
            $token = $this->tokens[$i];

            if (! $token['block']) {
                $i++;

                continue;
            }

            array_push($parts, ...$this->formatRun($runStart, $i, $wrap));

            $close = $this->closers[$i] ?? null;

            if ($token['type'] === 'open' && $close !== null && $close < $to) {
                $parts[] = $this->formatElement($i, $close, $depth);
                $i = $close + 1;
            } else {
                $parts[] = $token['raw'];
                $i++;
            }

            $runStart = $i;
        }

        array_push($parts, ...$this->formatRun($runStart, $to, $wrap));

        return $parts;
    }

    private function formatElement(int $open, int $close, int $depth): string
    {
        $tag = $this->tokens[$open]['raw'];
        $end = $this->tokens[$close]['raw'];
        $name = $this->tokens[$open]['name'];
        $original = $tag.$this->rawBetween($open + 1, $close).$end;

        if ($depth >= self::MAX_DEPTH) {
            return $original;
        }

        if (in_array($name, self::TEXT, true)) {
            return $this->formatTextElement($open, $close, $tag, $end, $name, $original);
        }

        if (in_array($name, self::STRUCTURE, true)) {
            return $this->formatStructure($open, $close, $tag, $end, $depth);
        }

        $blankLine = $this->hasBlankLine($open + 1, $close);
        $wrap = $name === 'blockquote'
            || $blankLine
            || (in_array($name, self::FLOW, true) && $this->hasBlockChild($open + 1, $close));

        $parts = $this->formatFlow($open + 1, $close, $wrap, $depth + 1);

        return $this->assemble($tag, $parts, $end, $original);
    }

    /**
     * @param  list<string>  $parts
     */
    private function assemble(string $tag, array $parts, string $end, string $original): string
    {
        $formatted = count($parts) > 1
            ? $tag."\n".implode("\n", $parts)."\n".$end
            : $tag.($parts[0] ?? '').$end;

        return $this->collapse($formatted) === $this->collapse($original) ? $original : $formatted;
    }

    /**
     * Whitespace around tags means nothing, so markup that only differs there
     * has not changed.
     */
    private function collapse(string $html): string
    {
        return (string) preg_replace('/\s*(<[^>]*>)\s*/', '$1', trim($html));
    }

    private function formatTextElement(int $open, int $close, string $tag, string $end, string $name, string $original): string
    {
        $paragraphs = $this->paragraphs($open + 1, $close);

        if ($name !== 'p') {
            $inner = implode("<br />\n<br />\n", $paragraphs);

            return $this->collapse($tag.$inner.$end) === $this->collapse($original) ? $original : $tag.$inner.$end;
        }

        $first = array_shift($paragraphs) ?? '';
        $html = $tag.$first.$end;

        foreach ($paragraphs as $paragraph) {
            $html .= "\n<p>".$paragraph.'</p>';
        }

        return $this->collapse($html) === $this->collapse($original) ? $original : $html;
    }

    private function formatStructure(int $open, int $close, string $tag, string $end, int $depth): string
    {
        $inner = '';
        $i = $open + 1;

        while ($i < $close) {
            $token = $this->tokens[$i];
            $childClose = $this->closers[$i] ?? null;

            if ($token['block'] && $token['type'] === 'open' && $childClose !== null && $childClose < $close) {
                $inner .= $this->formatElement($i, $childClose, $depth + 1);
                $i = $childClose + 1;
            } else {
                $inner .= $token['raw'];
                $i++;
            }
        }

        return $tag.$inner.$end;
    }

    /**
     * @return list<string>
     */
    private function formatRun(int $from, int $to, bool $wrap): array
    {
        $paragraphs = $this->paragraphs($from, $to);
        $parts = [];

        foreach ($paragraphs as $paragraph) {
            $parts[] = $wrap && ! $this->isOnlyComments($paragraph) ? '<p>'.$paragraph.'</p>' : $paragraph;
        }

        return $parts;
    }

    /**
     * Builds the paragraphs of a run of text and inline elements. A blank line
     * ends a paragraph, unless an inline element is still open: splitting
     * there would produce crossing tags, so it becomes two line breaks instead.
     *
     * @return list<string>
     */
    private function paragraphs(int $from, int $to): array
    {
        $paragraphs = [];
        $current = '';
        $pendingBreak = false;
        $inlineDepth = 0;

        for ($i = $from; $i < $to; $i++) {
            $token = $this->tokens[$i];

            if ($token['type'] !== 'text') {
                if (! $token['block']) {
                    $inlineDepth += match ($token['type']) {
                        'open' => 1,
                        'close' => $inlineDepth > 0 ? -1 : 0,
                        default => 0,
                    };
                }

                $current = $this->flushBreak($current, $pendingBreak).$token['raw'];
                $pendingBreak = false;

                continue;
            }

            $pieces = preg_split(self::BLANK_LINE, $token['raw']) ?: [$token['raw']];

            foreach ($pieces as $index => $piece) {
                if ($index > 0) {
                    if ($inlineDepth === 0) {
                        if (trim($current) !== '') {
                            $paragraphs[] = $current;
                        }

                        $current = '';
                        $pendingBreak = false;
                    } else {
                        $current .= "<br />\n<br />\n";
                        $pendingBreak = false;
                    }
                }

                foreach (explode("\n", $piece) as $lineIndex => $line) {
                    if ($lineIndex > 0) {
                        $pendingBreak = true;
                        $line = ltrim($line, " \t");
                    }

                    if ($line === '') {
                        continue;
                    }

                    if (trim($line) === '') {
                        $current .= $pendingBreak || $current === '' ? '' : $line;

                        continue;
                    }

                    $current = $this->flushBreak($current, $pendingBreak).$line;
                    $pendingBreak = false;
                }
            }
        }

        if (trim($current) !== '') {
            $paragraphs[] = $current;
        }

        return array_map(trim(...), $paragraphs);
    }

    private function flushBreak(string $current, bool $pendingBreak): string
    {
        if (! $pendingBreak || $current === '') {
            return ltrim($current, " \t") === '' ? '' : $current;
        }

        if (preg_match('/<br\s*\/?>\s*$/i', $current) === 1) {
            return $current."\n";
        }

        return rtrim($current, " \t")."<br />\n";
    }

    private function isOnlyComments(string $paragraph): bool
    {
        return trim((string) preg_replace('/<!--.*?-->/s', '', $paragraph)) === '';
    }

    private function rawBetween(int $from, int $to): string
    {
        $raw = '';

        for ($i = $from; $i < $to; $i++) {
            $raw .= $this->tokens[$i]['raw'];
        }

        return $raw;
    }

    private function hasBlankLine(int $from, int $to): bool
    {
        for ($i = $from; $i < $to; $i++) {
            $token = $this->tokens[$i];

            if ($token['block'] && $token['type'] === 'open' && ($close = $this->closers[$i] ?? null) !== null && $close < $to) {
                $i = $close;

                continue;
            }

            if ($token['type'] === 'text' && trim($token['raw']) !== '' && preg_match(self::BLANK_LINE, trim($token['raw'])) === 1) {
                return true;
            }
        }

        return false;
    }

    private function hasBlockChild(int $from, int $to): bool
    {
        for ($i = $from; $i < $to; $i++) {
            if ($this->tokens[$i]['block']) {
                return true;
            }
        }

        return false;
    }
}
