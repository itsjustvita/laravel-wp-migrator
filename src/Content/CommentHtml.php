<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Content;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use ItsJustVita\WpMigrator\Support\SafeUrl;

/**
 * Reduces comment HTML to the tags WP allows in comments. Comments come
 * from visitors, so everything else is removed: unknown tags keep their
 * text, script-like elements disappear with their content, and links only
 * keep web and mail addresses.
 */
final class CommentHtml
{
    /** @var array<string, list<string>> tag => allowed attributes */
    private const array ALLOWED = [
        'a' => ['href', 'title'],
        'abbr' => ['title'],
        'acronym' => ['title'],
        'b' => [],
        'blockquote' => ['cite'],
        'br' => [],
        'cite' => [],
        'code' => [],
        'del' => ['datetime'],
        'em' => [],
        'i' => [],
        'q' => ['cite'],
        's' => [],
        'strike' => [],
        'strong' => [],
    ];

    /** Elements removed together with everything inside them. */
    private const array REMOVED = ['script', 'style', 'iframe', 'object', 'embed', 'applet', 'template', 'noscript', 'svg', 'math', 'title', 'textarea', 'select', 'head'];

    private const array URL_ATTRIBUTES = ['href', 'cite'];

    private const array URL_SCHEMES = ['http', 'https', 'mailto'];

    private const int MAX_DEPTH = 64;

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            // A "<" that starts no tag is text; the parser would swallow it.
            $html = (string) preg_replace('/<(?![a-zA-Z\/!?])/', '&lt;', mb_scrub($html, 'UTF-8'));

            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><body>'.$html.'</body>',
                LIBXML_NONET | LIBXML_COMPACT,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $body = $document->getElementsByTagName('body')->item(0);

        return $loaded && $body !== null ? self::children($body, 0) : htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function children(DOMNode $node, int $depth): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= self::node($child, $depth);
        }

        return $html;
    }

    private static function node(DOMNode $node, int $depth): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->wholeText, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $name = strtolower($node->tagName);

        if (in_array($name, self::REMOVED, true)) {
            return '';
        }

        if ($depth >= self::MAX_DEPTH || ! isset(self::ALLOWED[$name])) {
            return $depth >= self::MAX_DEPTH ? htmlspecialchars($node->textContent, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') : self::children($node, $depth + 1);
        }

        if ($name === 'br') {
            return '<br>';
        }

        $attributes = '';

        foreach (self::ALLOWED[$name] as $attribute) {
            if (! $node->hasAttribute($attribute)) {
                continue;
            }

            $value = $node->getAttribute($attribute);

            if (in_array($attribute, self::URL_ATTRIBUTES, true) && ! SafeUrl::isSafe($value, self::URL_SCHEMES)) {
                continue;
            }

            $attributes .= ' '.$attribute.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        // A link without a usable address is just its text.
        if ($name === 'a' && ! str_contains($attributes, ' href=')) {
            return self::children($node, $depth + 1);
        }

        return '<'.$name.$attributes.'>'.self::children($node, $depth + 1).'</'.$name.'>';
    }
}
