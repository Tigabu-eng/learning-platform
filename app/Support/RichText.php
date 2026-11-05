<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Text written in the editor is stored as HTML. Older content (and imported questions) may be
 * Markdown or plain text. Everything is passed through an allow-list sanitizer before display.
 */
class RichText
{
    /** Safe HTML for display. $markdown: treat non-HTML input as Markdown (lessons, descriptions) or plain text (questions). */
    public static function html(?string $value, bool $markdown = true): HtmlString
    {
        return new HtmlString(self::sanitize(self::toHtml($value, $markdown)));
    }

    /** The value the editor starts from: existing Markdown/plain text converted to HTML. */
    public static function forEditor(?string $value, bool $markdown = true): string
    {
        return self::sanitize(self::toHtml($value, $markdown));
    }

    /** Clean HTML to store from an editor submission. Empty editors become null. */
    public static function clean(?string $value): ?string
    {
        if ($value === null || ! self::isHtml($value)) return $value === null || trim($value) === '' ? null : $value;
        $html = self::sanitize($value);
        return trim(html_entity_decode(strip_tags($html))) === '' && ! str_contains($html, '<img') ? null : $html;
    }

    /** Plain text, for summaries, comparisons and emails. */
    public static function plain(?string $value): string
    {
        $value = (string) $value;
        if (! self::isHtml($value)) return trim($value);
        $value = preg_replace('#<(br|/p|/li|/h[1-6]|/blockquote)\s*/?>#i', "$0\n", $value);
        return trim(preg_replace("/\n{2,}/", "\n", html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)));
    }

    public static function isHtml(?string $value): bool
    {
        return (bool) preg_match('#^\s*<(p|h[1-6]|ul|ol|blockquote|table|img|div|pre|hr|strong|em|u|s|a|br)\b#i', (string) $value);
    }

    private static function toHtml(?string $value, bool $markdown): string
    {
        $value = (string) $value;
        if ($value === '' || self::isHtml($value)) return $value;
        return $markdown
            ? Str::markdown($value, ['html_input' => 'escape', 'allow_unsafe_links' => false])
            : '<p>'.nl2br(e($value), false).'</p>';
    }

    private static function sanitize(string $html): string
    {
        static $sanitizer;
        $sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowElement('p')->allowElement('br')->allowElement('hr')
                ->allowElement('strong')->allowElement('b')->allowElement('em')->allowElement('i')
                ->allowElement('u')->allowElement('s')->allowElement('code')->allowElement('pre')
                ->allowElement('h2')->allowElement('h3')->allowElement('h4')
                ->allowElement('ul')->allowElement('ol', ['start'])->allowElement('li')->allowElement('blockquote')
                ->allowElement('a', ['href', 'title'])
                ->allowElement('img', ['src', 'alt', 'title'])
                ->allowElement('table')->allowElement('thead')->allowElement('tbody')->allowElement('tr')
                ->allowElement('th', ['colspan', 'rowspan'])->allowElement('td', ['colspan', 'rowspan'])
                ->allowElement('colgroup')->allowElement('col')
                ->allowLinkSchemes(['https', 'http', 'mailto'])
                ->allowMediaSchemes(['https', 'http'])
                ->allowRelativeLinks()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(500_000)
        );
        // Markdown's h1 becomes h2: the page title is the only h1.
        return $sanitizer->sanitize(preg_replace('#<(/?)h1\b#i', '<$1h2', $html));
    }
}
