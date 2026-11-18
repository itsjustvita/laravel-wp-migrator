<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Detection\Suspicious;

/**
 * Looks for signs of injected code in HTML: scripts, event handlers, links
 * that run script, frames from unknown sites, links hidden with CSS and
 * obfuscated code. These are common on hacked sites, and the import keeps
 * them, so the new site would serve them. Each check reports a few details,
 * such as the hosts a script or frame comes from.
 */
final class ContentChecks
{
    /** The order the checks are reported in. */
    public const array KEYS = ['scripts', 'event-handlers', 'script-links', 'frames', 'hidden-links', 'obfuscation'];

    private const int DETAILS = 5;

    /** Attributes named after DOM events. Others starting with "on" are not handlers. */
    private const array EVENTS = [
        'abort', 'afterprint', 'animationend', 'animationiteration', 'animationstart', 'auxclick', 'beforeinput',
        'beforeprint', 'beforeunload', 'begin', 'blur', 'canplay', 'canplaythrough', 'change', 'click', 'close',
        'contextmenu', 'copy', 'cuechange', 'cut', 'dblclick', 'drag', 'dragend', 'dragenter', 'dragleave',
        'dragover', 'dragstart', 'drop', 'durationchange', 'end', 'ended', 'error', 'focus', 'focusin', 'focusout',
        'formdata', 'hashchange', 'input', 'invalid', 'keydown', 'keypress', 'keyup', 'load', 'loadeddata',
        'loadedmetadata', 'loadstart', 'message', 'mousedown', 'mouseenter', 'mouseleave', 'mousemove', 'mouseout',
        'mouseover', 'mouseup', 'mousewheel', 'offline', 'online', 'pagehide', 'pageshow', 'paste', 'pause', 'play',
        'playing', 'pointercancel', 'pointerdown', 'pointerenter', 'pointerleave', 'pointermove', 'pointerout',
        'pointerover', 'pointerup', 'popstate', 'progress', 'ratechange', 'repeat', 'reset', 'resize', 'scroll',
        'scrollend', 'search', 'seeked', 'seeking', 'select', 'selectionchange', 'selectstart', 'show', 'stalled',
        'storage', 'submit', 'suspend', 'timeupdate', 'toggle', 'touchcancel', 'touchend', 'touchmove',
        'touchstart', 'transitioncancel', 'transitionend', 'transitionrun', 'transitionstart', 'unload',
        'volumechange', 'waiting', 'wheel',
    ];

    /** Sites whose players and maps are commonly embedded. */
    private const array KNOWN_FRAME_HOSTS = [
        'youtube.com', 'youtube-nocookie.com', 'vimeo.com', 'google.com', 'dailymotion.com', 'soundcloud.com',
        'spotify.com', 'facebook.com', 'instagram.com', 'twitter.com', 'x.com', 'twitch.tv', 'loom.com',
        'wistia.net', 'wistia.com', 'openstreetmap.org', 'calendly.com', 'typeform.com', 'tiktok.com',
        'linkedin.com', 'slideshare.net', 'issuu.com', 'mixcloud.com', 'ted.com', 'bandcamp.com',
    ];

    private const string HIDING = '(?:display\s*:\s*none|visibility\s*:\s*hidden|font-size\s*:\s*0(?:px|em|rem|pt|%)?\s*(?:;|$)|(?:left|top|text-indent)\s*:\s*-\d{3,})';

    /** Calls that hide what code does, by name and pattern. */
    private const array OBFUSCATION = [
        'eval' => '/(?<![\w.$])eval\s*\(/i',
        'atob' => '/(?<![\w.$])atob\s*\(/i',
        'fromCharCode' => '/fromCharCode\s*\(/i',
        'unescape' => '/(?<![\w.$])unescape\s*\(/i',
        'base64_decode' => '/base64_decode\s*\(/i',
        'gzinflate' => '/gzinflate\s*\(/i',
        'document.write' => '/document\s*\.\s*write\s*\(/i',
    ];

    public function __construct(private readonly ?string $siteHost = null) {}

    /** One quick pass that every finding needs to match, so clean content costs one regex. */
    private const string QUICK = '/<script|<iframe|javascript|vbscript|data\s*:\s*text|display\s*:\s*none|visibility\s*:\s*hidden|font-size\s*:\s*0|(?:left|top|text-indent)\s*:\s*-|eval\s*\(|atob\s*\(|fromCharCode|unescape\s*\(|base64_decode|gzinflate|document\s*\.\s*write|[\s"\'\/]on[a-z]+\s*=/i';

    /**
     * The checks that found something, with up to five details each.
     *
     * @return array<string, list<string>>
     */
    public function find(string $html): array
    {
        if (preg_match(self::QUICK, $html) !== 1) {
            return [];
        }

        $found = [
            'scripts' => $this->scripts($html),
            'event-handlers' => $this->eventHandlers($html),
            'script-links' => $this->scriptLinks($html),
            'frames' => $this->frames($html),
            'hidden-links' => $this->hiddenLinks($html),
            'obfuscation' => $this->obfuscation($html),
        ];

        return array_map(
            fn (array $details): array => array_slice(array_values(array_unique($details)), 0, self::DETAILS),
            array_filter($found, fn (array $details): bool => $details !== []),
        );
    }

    /**
     * @return list<string>
     */
    private function scripts(string $html): array
    {
        preg_match_all('/<script\b([^>]*)>/i', $html, $matches);

        return array_map(fn (string $attributes): string => $this->host($this->attribute($attributes, 'src')) ?? 'inline', $matches[1]);
    }

    /**
     * @return list<string>
     */
    private function eventHandlers(string $html): array
    {
        preg_match_all('/<[a-z][a-z0-9-]*\b[^>]*>/i', $html, $tags);
        $handlers = [];

        foreach ($tags[0] as $tag) {
            preg_match_all('/[\s"\'\/]on([a-z]+)\s*=/i', $tag, $matches);

            foreach ($matches[1] as $event) {
                if (in_array(strtolower($event), self::EVENTS, true)) {
                    $handlers[] = 'on'.strtolower($event);
                }
            }
        }

        return $handlers;
    }

    /**
     * @return list<string>
     */
    private function scriptLinks(string $html): array
    {
        preg_match_all('/\b(?:href|src|action|formaction|data|xlink:href)\s*=\s*["\']?[\x00-\x20]*(javascript|vbscript|data[\x00-\x20]*:[\x00-\x20]*text\/html)/i', $html, $matches);

        return array_map(fn (string $scheme): string => strtolower((string) preg_replace('/[\x00-\x20]+/', '', $scheme)).(str_starts_with(strtolower($scheme), 'data') ? '' : ':'), $matches[1]);
    }

    /**
     * @return list<string>
     */
    private function frames(string $html): array
    {
        preg_match_all('/<iframe\b([^>]*)>/i', $html, $matches);
        $hosts = [];

        foreach ($matches[1] as $attributes) {
            $host = $this->host($this->attribute($attributes, 'src'));

            if ($host !== null && ! $this->known($host)) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    /**
     * Links to other sites inside elements hidden with CSS, a sign of SEO spam.
     *
     * @return list<string>
     */
    private function hiddenLinks(string $html): array
    {
        $pattern = '/<(div|span|p|section|font|aside|small)\b[^>]*\bstyle\s*=\s*("[^"]*'.self::HIDING.'[^"]*"|\'[^\']*'.self::HIDING.'[^\']*\')[^>]*>(.*?)<\/\1\s*>/is';
        preg_match_all($pattern, $html, $elements);
        $hosts = [];

        foreach ($elements[3] as $inner) {
            preg_match_all('/<a\b([^>]*)>/i', $inner, $links);

            foreach ($links[1] as $attributes) {
                $host = $this->host($this->attribute($attributes, 'href'));

                if ($host !== null) {
                    $hosts[] = $host;
                }
            }
        }

        return $hosts;
    }

    /**
     * @return list<string>
     */
    private function obfuscation(string $html): array
    {
        $text = (string) preg_replace('/<(pre|code)\b[^>]*>.*?<\/\1\s*>/is', '', $html);
        $found = [];

        foreach (self::OBFUSCATION as $name => $pattern) {
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) === 1) {
                $found[$match[0][1]] = $name;
            }
        }

        ksort($found);

        return array_values($found);
    }

    private function attribute(string $attributes, string $name): ?string
    {
        if (preg_match('/(?:^|\s)'.preg_quote($name, '/').'\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attributes, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1] !== '' ? $matches[1] : (($matches[2] ?? '') !== '' ? $matches[2] : ($matches[3] ?? '')), ENT_QUOTES | ENT_HTML5);
    }

    /**
     * The host of an absolute URL on another site, or null.
     */
    private function host(?string $url): ?string
    {
        $url = trim((string) $url);

        if (preg_match('#^(?:https?:)?//([^/?\#:\s]+)#i', $url, $matches) !== 1) {
            return null;
        }

        $host = strtolower($matches[1]);

        return $host === $this->siteHost || $host === 'www.'.$this->siteHost || 'www.'.$host === $this->siteHost ? null : $host;
    }

    private function known(string $host): bool
    {
        foreach (self::KNOWN_FRAME_HOSTS as $known) {
            if ($host === $known || str_ends_with($host, '.'.$known)) {
                return true;
            }
        }

        return false;
    }
}
