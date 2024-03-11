<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * @internal
 */
#[CoversClass(HtmlSanitizer::class)]
class HtmlSanitizerTest extends TestCase
{
    public function testAllowDisablingHtmlSanitizer(): void
    {
        $sets = $this->getDefaultSets();
        $fieldSets = $this->getDefaultFieldsSets();

        $sanitizer = new HtmlSanitizer(null, false, $sets, $fieldSets, false);

        $unfilteredString = '<invalid-tag>Lorem Ipsum dolor sit amet</invalid-tag>';
        $filteredString = $sanitizer->sanitize($unfilteredString, null);

        static::assertSame($unfilteredString, $filteredString);

        $sanitizer = new HtmlSanitizer(null, false, $sets, $fieldSets, true);
        $filteredString = $sanitizer->sanitize($unfilteredString, null);

        static::assertSame('Lorem Ipsum dolor sit amet', $filteredString);
    }

    public function testAllowedByFieldSetConfig(): void
    {
        $sets = $this->getDefaultSets();
        $fieldSets = $this->getDefaultFieldsSets();

        $sanitizer = new HtmlSanitizer(null, false, $sets, $fieldSets);

        $unfilteredString = '<a href=\"%target%\" data-toggle=\"modal\" data-bs-toggle=\"modal\" data-target=\"%target%\" data-bs-target=\"%target%\">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"';

        $filteredString = $sanitizer->sanitize($unfilteredString, null, false, 'test.bootstrap');

        static::assertSame('<a href="\&quot;%target%\&quot;" data-bs-toggle="\&quot;modal\&quot;" data-bs-target="\&quot;%target%\&quot;">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"', $filteredString);

        $unfilteredString = '<a href=\"%target%\" data-bs-toggle=\"modal\" data-bs-non-exist="foo">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"';
        $filteredString = $sanitizer->sanitize($unfilteredString, null, false, 'test.bootstrap');

        static::assertSame('<a href="\&quot;%target%\&quot;" data-bs-toggle="\&quot;modal\&quot;">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"', $filteredString);
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    private function getDefaultFieldsSets(): array
    {
        return [
            'test.bootstrap' => [
                'sets' => [
                    'basic',
                    'bootstrap',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getDefaultSets(): array
    {
        return [
            'basic' => [
                'tags' => [
                    'a',
                    'abbr',
                    'acronym',
                    'address',
                    'b',
                    'bdo',
                    'big',
                    'blockquote',
                    'br',
                    'caption',
                    'center',
                    'cite',
                    'code',
                    'col',
                    'colgroup',
                    'dd',
                    'del',
                    'dfn',
                    'dir',
                    'div',
                    'dl',
                    'dt',
                    'em',
                    'font',
                    'h1',
                    'h2',
                    'h3',
                    'h4',
                    'h5',
                    'h6',
                    'hr',
                    'i',
                    'ins',
                    'kbd',
                    'li',
                    'menu',
                    'ol',
                    'p',
                    'pre',
                    'q',
                    's',
                    'samp',
                    'small',
                    'span',
                    'strike',
                    'strong',
                    'sub',
                    'sup',
                    'table',
                    'tbody',
                    'td',
                    'tfoot',
                    'th',
                    'thead',
                    'tr',
                    'tt',
                    'u',
                    'ul',
                    'var',
                ],
                'attributes' => [
                    'align',
                    'bgcolor',
                    'border',
                    'cellpadding',
                    'cellspacing',
                    'cite',
                    'class',
                    'clear',
                    'color',
                    'colspan',
                    'dir',
                    'face',
                    'frame',
                    'height',
                    'href',
                    'id',
                    'lang',
                    'name',
                    'noshade',
                    'nowrap',
                    'rel',
                    'rev',
                    'rowspan',
                    'scope',
                    'size',
                    'span',
                    'start',
                    'style',
                    'summary',
                    'title',
                    'type',
                    'valign',
                    'value',
                    'width',
                    'target',
                ],
                'options' => [
                    'Attr.AllowedFrameTargets' => [
                        'values' => [
                            '_blank',
                            '_self',
                            '_parent',
                            '_top',
                        ],
                    ],
                    'Attr.AllowedRel' => [
                        'values' => [
                            'nofollow',
                            'print',
                        ],
                    ],
                ],
                'custom_attributes' => [
                ],
            ],
            'bootstrap' => [
                'attributes' => [
                    'role',
                    'aria-label',
                    'aria-labelledly',
                    'aria-current',
                    'aria-expanded',
                    'aria-controls',
                    'aria-hidden',
                    'aria-describedby',
                    'tabindex',
                    'aria-modal',
                    'data-bs-toggle',
                    'data-bs-target',
                    'data-bs-dismiss',
                    'data-bs-slide',
                    'data-bs-slide-to',
                    'data-bs-parent',
                    'data-bs-config',
                    'data-bs-content',
                    'data-bs-spy',
                ],
                'custom_attributes' => [
                    [
                        'tags' => [
                            'a',
                            'span',
                        ],
                        'attributes' => [
                            'role',
                            'href',
                            'aria-label',
                            'aria-labelledly',
                            'aria-current',
                            'aria-expanded',
                            'aria-controls',
                            'aria-hidden',
                            'aria-describedby',
                            'tabindex',
                            'aria-modal',
                            'data-bs-toggle',
                            'data-bs-target',
                            'data-bs-dismiss',
                            'data-bs-slide',
                            'data-bs-slide-to',
                            'data-bs-parent',
                            'data-bs-config',
                            'data-bs-content',
                            'data-bs-spy',
                        ],
                    ],
                ],
            ],
        ];
    }
}
