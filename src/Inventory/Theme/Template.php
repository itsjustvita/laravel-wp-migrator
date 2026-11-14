<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Inventory\Theme;

/**
 * A theme file and its place in the template hierarchy.
 *
 * kind is one of: front-page, home, index, singular, single, page, archive,
 * term, author, date, search, not-found, attachment, custom (a page
 * template chosen per post), partial (header, footer, sidebar, …) and part
 * (a reusable template part).
 */
final readonly class Template
{
    /**
     * @param  string|null  $subject  the post type, taxonomy, partial name or template name it is for
     * @param  string|null  $detail  the slug or ID it is limited to
     * @param  list<string>  $postTypes  post types a custom template can be chosen for
     * @param  string  $source  "theme" or "child"
     */
    public function __construct(
        public string $path,
        public string $kind,
        public ?string $subject = null,
        public ?string $detail = null,
        public array $postTypes = [],
        public string $source = 'theme',
    ) {}

    public function withSource(string $source): self
    {
        return new self($this->path, $this->kind, $this->subject, $this->detail, $this->postTypes, $source);
    }

    public function description(): string
    {
        $detail = $this->detail === null ? '' : ' "'.$this->detail.'"';

        return match ($this->kind) {
            'front-page' => 'Front page',
            'home' => 'Blog index',
            'index' => 'Fallback for everything',
            'singular' => 'Any single post',
            'single' => 'Single '.($this->subject ?? 'post').$detail,
            'page' => 'Page'.$detail,
            'archive' => $this->subject === null ? 'Archive' : 'Archive of '.$this->subject,
            'term' => $this->termDescription(),
            'author' => 'Author archive'.$detail,
            'date' => 'Date archive',
            'search' => 'Search results',
            'not-found' => 'Page not found',
            'attachment' => 'Attachment page'.$detail,
            'custom' => 'Page template "'.$this->subject.'"',
            'partial' => 'Partial '.$this->subject.$detail,
            'part' => 'Template part '.$this->subject,
            default => $this->path,
        };
    }

    private function termDescription(): string
    {
        $taxonomy = match ($this->subject) {
            null => 'term',
            'category' => 'category',
            'post_tag' => 'tag',
            default => $this->subject,
        };

        return $this->detail === null
            ? 'Archive of a '.$taxonomy
            : 'Archive of the '.$taxonomy.' "'.$this->detail.'"';
    }
}
