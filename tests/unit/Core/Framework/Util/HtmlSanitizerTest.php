<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Test\Stub\Framework\Util\NullRawDefinitionHtmlPurifierConfig;
use Shopware\Core\Test\Stub\Framework\Util\StaticHtmlPurifierConfigProvider;

/**
 * @internal
 */
#[CoversClass(HtmlSanitizer::class)]
class HtmlSanitizerTest extends TestCase
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $sets;

    /**
     * @var array<string, array<string, list<string>>>
     */
    private array $fieldSets;

    protected function setUp(): void
    {
        $this->sets = $this->getDefaultSets();
        $this->fieldSets = $this->getDefaultFieldsSets();
    }

    #[TestDox('Honors the enabled flag: disabled returns input untouched, enabled strips invalid tags')]
    #[DataProvider('enabledFlagProvider')]
    public function testHonorsEnabledFlag(bool $enabled, string $expected): void
    {
        $sanitizer = new HtmlSanitizer(cacheEnabled: false, sets: $this->sets, fieldSets: $this->fieldSets, enabled: $enabled);

        static::assertSame($expected, $sanitizer->sanitize('<invalid-tag>Lorem Ipsum dolor sit amet</invalid-tag>', null));
    }

    /**
     * @param list<array<string, mixed>>|null $customTags
     */
    #[TestDox('Registered custom tags and attributes survive purification; unregistered ones are stripped')]
    #[DataProvider('customTagsProvider')]
    public function testRegisteredCustomElementsSurvivePurification(string $input, ?array $customTags, string $expected): void
    {
        $sets = $this->sets;

        if ($customTags !== null) {
            $sets['basic']['custom_tags'] = $customTags;
        }

        $sanitizer = new HtmlSanitizer(cacheEnabled: false, sets: $sets, fieldSets: $this->fieldSets);

        static::assertSame($expected, $sanitizer->sanitize($input, null));
    }

    #[TestDox('Applies the configured cache dir as Cache.SerializerPath on the purifier config')]
    public function testAppliesCacheSerializerPathWhenCacheDirIsProvided(): void
    {
        $cacheDir = '/virtual/html-sanitizer-cache';
        $config = \HTMLPurifier_Config::createDefault();

        $sanitizer = new HtmlSanitizer(
            cacheDir: $cacheDir,
            cacheEnabled: false,
            sets: $this->sets,
            fieldSets: $this->fieldSets,
            configProvider: new StaticHtmlPurifierConfigProvider($config),
        );

        $sanitizer->sanitize('<b>bold</b>');

        static::assertSame($cacheDir, $config->get('Cache.SerializerPath'));
    }

    #[TestDox('Leaves Cache.SerializerPath at its HTMLPurifier default when no cache dir is configured')]
    public function testDoesNotApplyCacheSerializerPathWhenCacheDirIsEmpty(): void
    {
        $config = \HTMLPurifier_Config::createDefault();

        $sanitizer = new HtmlSanitizer(
            cacheEnabled: false,
            sets: $this->sets,
            fieldSets: $this->fieldSets,
            configProvider: new StaticHtmlPurifierConfigProvider($config),
        );

        $sanitizer->sanitize('<b>bold</b>');

        static::assertNull($config->get('Cache.SerializerPath'));
    }

    #[TestDox('Returns the base config (input untouched) when HTMLPurifier raw HTML definition is unavailable')]
    public function testReturnsBaseConfigWhenRawHtmlDefinitionIsUnavailable(): void
    {
        $stubConfig = new NullRawDefinitionHtmlPurifierConfig();

        $sanitizer = new HtmlSanitizer(
            cacheEnabled: false,
            sets: $this->sets,
            fieldSets: $this->fieldSets,
            configProvider: new StaticHtmlPurifierConfigProvider($stubConfig),
        );

        $result = $sanitizer->sanitize('<b>bold</b>', null);

        static::assertSame(1, $stubConfig->rawDefinitionCalls);
        static::assertSame('<b>bold</b>', $result);
    }

    #[TestDox('Field-set-specific config limits allowed attributes to those declared in the combined sets')]
    #[DataProvider('fieldSetConfigProvider')]
    public function testFieldSetConfigLimitsAllowedAttributes(string $input, string $expected): void
    {
        $sanitizer = new HtmlSanitizer(cacheEnabled: false, sets: $this->sets, fieldSets: $this->fieldSets);

        static::assertSame($expected, $sanitizer->sanitize($input, null, false, 'test.bootstrap'));
    }

    #[TestDox('Decodes HTML entities (including double-encoded ones) back to their character form')]
    #[DataProvider('entityProvider')]
    public function testDecodesHtmlEntities(string $input, string $expected): void
    {
        $sanitizer = new HtmlSanitizer(cacheEnabled: false, sets: $this->sets, fieldSets: $this->fieldSets);

        static::assertSame($expected, $sanitizer->sanitize($input));
    }

    public static function enabledFlagProvider(): \Generator
    {
        yield 'disabled keeps invalid tag' => [
            false,
            '<invalid-tag>Lorem Ipsum dolor sit amet</invalid-tag>',
        ];

        yield 'enabled strips invalid tag' => [
            true,
            'Lorem Ipsum dolor sit amet',
        ];
    }

    public static function customTagsProvider(): \Generator
    {
        $tagWithoutAttributes = [
            [
                'tag' => 'custom-element',
                'type' => 'Block',
                'contents' => 'Flow',
                'attr_collections' => ['Common'],
                'attributes' => [],
            ],
        ];

        $tagWithAttribute = [
            [
                'tag' => 'custom-element',
                'type' => 'Block',
                'contents' => 'Flow',
                'attr_collections' => ['Common'],
                'attributes' => [
                    'testtribute',
                ],
            ],
        ];

        yield 'unregistered custom tag is stripped' => [
            '<custom-element>Lorem Ipsum dolor sit amet</custom-element>',
            null,
            'Lorem Ipsum dolor sit amet',
        ];

        yield 'registered custom tag is preserved' => [
            '<custom-element>Lorem Ipsum dolor sit amet</custom-element>',
            $tagWithoutAttributes,
            '<custom-element>Lorem Ipsum dolor sit amet</custom-element>',
        ];

        yield 'unregistered custom attribute is stripped' => [
            '<custom-element testtribute="test1234">Lorem Ipsum dolor sit amet</custom-element>',
            $tagWithoutAttributes,
            '<custom-element>Lorem Ipsum dolor sit amet</custom-element>',
        ];

        yield 'registered custom attribute is preserved' => [
            '<custom-element testtribute="test1234">Lorem Ipsum dolor sit amet</custom-element>',
            $tagWithAttribute,
            '<custom-element testtribute="test1234">Lorem Ipsum dolor sit amet</custom-element>',
        ];
    }

    public static function fieldSetConfigProvider(): \Generator
    {
        yield 'bootstrap set drops non-bs data attributes' => [
            '<a href=\"%target%\" data-toggle=\"modal\" data-bs-toggle=\"modal\" data-target=\"%target%\" data-bs-target=\"%target%\">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"',
            '<a href="\&quot;%target%\&quot;" data-bs-toggle="\&quot;modal\&quot;" data-bs-target="\&quot;%target%\&quot;">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"',
        ];

        yield 'bootstrap set drops unknown data-bs attribute' => [
            '<a href=\"%target%\" data-bs-toggle=\"modal\" data-bs-non-exist="foo">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"',
            '<a href="\&quot;%target%\&quot;" data-bs-toggle="\&quot;modal\&quot;">Klicken Sie hier</a> um alle Ihre persönlichen Daten zu löschen"',
        ];
    }

    public static function entityProvider(): \Generator
    {
        yield 'double encoded umlaut' => [
            'Luxuri&amp;ouml;ser Herrenmantel in Grau.',
            'Luxuriöser Herrenmantel in Grau.',
        ];

        yield 'single encoded umlaut' => [
            'Luxuri&ouml;ser Herrenmantel in Grau.',
            'Luxuriöser Herrenmantel in Grau.',
        ];

        yield 'plain text' => [
            'Luxuriöser Herrenmantel in Grau.',
            'Luxuriöser Herrenmantel in Grau.',
        ];

        yield 'quotes' => [
            'String with &quot;quotes&quot;',
            'String with "quotes"',
        ];

        yield 'double encoded quotes' => [
            'String with &amp;quot;quotes&amp;quot;',
            'String with "quotes"',
        ];

        yield 'allowed tag' => [
            '&lt;b&gt;Bold&lt;/b&gt;',
            '<b>Bold</b>',
        ];
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
