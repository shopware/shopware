<?php declare(strict_types=1);

namespace Shopware\Storefront\Storybook;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * Sanitizes story-authored block HTML before it is rendered in the Storybook document.
 *
 * @internal
 */
#[Package('discovery')]
class StorybookHtmlSanitizer
{
    private const ALLOWED_ARIA_ATTRIBUTES = [
        'aria-label', 'aria-hidden', 'aria-current', 'aria-expanded', 'aria-pressed', 'role',
    ];

    /**
     * INVARIANT: Keep the preview's markup policy independent of globally configured sanitizer sets.
     * Elements and attributes outside this list, including authored stylesheets, are removed.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ELEMENTS = [
        '*' => [
            'class', 'title', 'lang', 'dir', ...self::ALLOWED_ARIA_ATTRIBUTES,
        ],
        'a' => ['href'],
        'abbr' => [],
        'address' => [],
        'article' => [],
        'aside' => [],
        'b' => [],
        'blockquote' => [],
        'br' => [],
        'button' => ['type', 'disabled'],
        'caption' => [],
        'code' => [],
        'dd' => [],
        'del' => [],
        'div' => [],
        'dl' => [],
        'dt' => [],
        'em' => [],
        'fieldset' => [],
        'figcaption' => [],
        'figure' => [],
        'footer' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'header' => [],
        'hr' => [],
        'i' => [],
        'img' => ['alt', 'width', 'height', 'src'],
        'input' => ['type', 'disabled', 'checked', 'placeholder', 'value'],
        'label' => [],
        'li' => [],
        'main' => [],
        'nav' => [],
        'ol' => [],
        'option' => ['selected', 'value'],
        'p' => [],
        'pre' => [],
        'q' => [],
        's' => [],
        'section' => [],
        'select' => [],
        'small' => [],
        'span' => [],
        'strong' => [],
        'sub' => [],
        'summary' => [],
        'sup' => [],
        'table' => [],
        'tbody' => [],
        'td' => [],
        'textarea' => [],
        'tfoot' => [],
        'th' => [],
        'thead' => [],
        'tr' => [],
        'u' => [],
        'ul' => [],
    ];

    /**
     * Returns sanitized block HTML with relative URLs resolved against the Shopware origin.
     */
    public function sanitize(string $html, string $requestOrigin): string
    {
        try {
            return $this->createHtmlSanitizer($requestOrigin)->sanitize($html, self::ALLOWED_ELEMENTS, field: 'storybook');
        } catch (\Throwable $exception) {
            throw StorybookHtmlSanitizerException::unableToSanitize($exception);
        }
    }

    protected function createHtmlSanitizer(string $requestOrigin): HtmlSanitizer
    {
        // WHY: Global settings can disable sanitization or permit markup unsafe in the Storybook document.
        return new HtmlSanitizer(
            cacheEnabled: false,
            sets: [
                'storybook' => [
                    // WHY: The allowlist permits these attributes, but HTMLPurifier also needs their definitions per tag.
                    'custom_attributes' => [[
                        'tags' => array_values(array_diff(array_keys(self::ALLOWED_ELEMENTS), ['*'])),
                        'attributes' => self::ALLOWED_ARIA_ATTRIBUTES,
                    ]],
                ],
            ],
            fieldSets: ['storybook' => ['sets' => ['storybook']]],
            enabled: true,
            configProvider: new StorybookHtmlPurifierConfigProvider($requestOrigin),
        );
    }
}
