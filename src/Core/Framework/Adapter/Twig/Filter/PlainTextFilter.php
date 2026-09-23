<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Filter;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
#[Package('framework')]
class PlainTextFilter extends AbstractExtension
{
    private const BLOCK_TAG_PATTERN = '/<\/?(?:address|article|aside|blockquote|br|caption|dd|details|div|dl|dt|figcaption|figure|footer|h[1-6]|header|hr|li|main|nav|ol|p|pre|section|summary|table|tbody|td|tfoot|th|thead|tr|ul)(?=[\s\/>])[^>]*>/i';

    /**
     * Only ASCII whitespace, so multibyte UTF-8 sequences are never split, regardless of the PCRE locale tables
     */
    private const WHITESPACE_PATTERN = '/[ \t\n\r\f\x0B]+/';

    public function getFilters(): array
    {
        return [
            new TwigFilter('sw_plain_text', $this->toPlainText(...)),
        ];
    }

    /**
     * Unlike `striptags`, block-level boundaries become a space, so `<p>One.</p><p>Two.</p>` results in `One. Two.`
     * HTML entities are kept encoded, so the result is as safe to output as the result of `striptags`.
     */
    public function toPlainText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $text = strip_tags(preg_replace(self::BLOCK_TAG_PATTERN, ' ', $html) ?? $html);

        return trim(preg_replace(self::WHITESPACE_PATTERN, ' ', $text) ?? $text);
    }
}
