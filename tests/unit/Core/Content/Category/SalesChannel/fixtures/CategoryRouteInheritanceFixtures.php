<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\SalesChannel\fixtures;

use PHPUnit\Framework\Attributes\Group;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Content\Category\SalesChannel\CategoryRouteTest;

/**
 * @internal
 *
 * @phpstan-import-type CmsInheritanceDataProviderIterator from CategoryRouteTest
 */
#[Group('store-api')]
#[Package('discovery')]
final class CategoryRouteInheritanceFixtures
{
    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function noOverridesDataProvider(): iterable
    {
        // EN templates
        yield 'EN Storefront, EN templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'AT Storefront, EN templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];

        // EN/DE templates
        yield 'EN Storefront, EN/DE templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/DE templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'de Template',
        ];
        yield 'AT Storefront, EN/DE templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'de Template',
        ];

        // EN/AT templates
        yield 'EN Storefront, EN/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'AT Storefront, EN/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'at Template',
        ];

        // All templates
        yield 'EN Storefront, EN/DE/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/DE/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'de Template',
        ];
        yield 'AT Storefront, EN/DE/AT templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'at Template',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function allOverridesDataProvider(): iterable
    {
        // EN templates
        yield 'EN Storefront, EN templates, All overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de', 'at',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, EN templates, All overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de', 'at',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Storefront, EN templates, All overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de', 'at',
                ],
            ],
            'expected' => 'at Override',
        ];

        // All templates
        yield 'EN Storefront, All templates, All overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'en', 'de', 'at',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, All templates, All overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'en', 'de', 'at',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Storefront, All templates, All overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'en', 'de', 'at',
                ],
            ],
            'expected' => 'at Override',
        ];
    }

    /**
     * Selection of override test cases, with multiple provided overrides.
     * Templates are obsolete and "randomly" chosen, since the other cases have shown, that overrides take priority over templates.
     *
     * @return CmsInheritanceDataProviderIterator
     */
    public static function mixedOverridesDataProvider(): iterable
    {
        // EN/DE overrides
        yield 'EN Storefront, EN templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, EN templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Storefront, EN templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'de Override',
        ];

        // EN/AT overrides
        yield 'EN Storefront, DE templates, EN/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en', 'at',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, DE templates, EN/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en', 'at',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'AT Storefront, DE templates, EN/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en', 'at',
                ],
            ],
            'expected' => 'at Override',
        ];

        // DE/AT overrides
        yield 'EN Storefront, AT templates, DE/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'de', 'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, AT templates, DE/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'de', 'at',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Storefront, AT templates, DE/AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'de', 'at',
                ],
            ],
            'expected' => 'at Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function enOverridesDataProvider(): iterable
    {
        // EN templates
        yield 'EN Storefront, EN templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, EN templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'AT Storefront, EN templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];

        // EN/DE templates
        yield 'EN Storefront, EN/DE templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, EN/DE templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'AT Storefront, EN/DE templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];

        // EN/AT templates
        yield 'EN Storefront, EN/AT templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, EN/AT templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'AT Storefront, EN/AT templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];

        // All templates
        yield 'EN Storefront, EN/DE/AT templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, EN/DE/AT templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'AT Storefront, EN/DE/AT templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function deOverridesDataProvider(): iterable
    {
        // EN templates
        yield 'EN Storefront, EN templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Storefront, EN templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];

        // EN/DE templates
        yield 'EN Storefront, EN/DE templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/DE templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Storefront, EN/DE templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];

        // EN/AT templates
        yield 'EN Storefront, EN/AT templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/AT templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Storefront, EN/AT templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];

        // All templates
        yield 'EN Storefront, EN/DE/AT templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/DE/AT templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];
        yield 'AT Storefront, EN/DE/AT templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function atOverridesDataProvider(): iterable
    {
        // EN templates
        yield 'EN Storefront, EN templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'AT Storefront, EN templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'at Override',
        ];

        // EN/DE templates
        yield 'EN Storefront, EN/DE templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/DE templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'de Template',
        ];
        yield 'AT Storefront, EN/DE templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'at Override',
        ];

        // EN/AT templates
        yield 'EN Storefront, EN/AT templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/AT templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'AT Storefront, EN/AT templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'at',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'at Override',
        ];

        // All templates
        yield 'EN Storefront, EN/DE/AT templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/DE/AT templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'de Template',
        ];
        yield 'AT Storefront, EN/DE/AT templates, AT overrides' => [
            'actual' => [
                'activeLanguageCode' => 'at',
                'hasTemplate' => [
                    'de', 'at',
                ],
                'hasSlotOverride' => [
                    'at',
                ],
            ],
            'expected' => 'at Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function duplicatesInLanguageChainDataProviderNoOverrides(): iterable
    {
        // EN templates
        yield 'EN Storefront, EN templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];

        // EN/DE templates
        yield 'EN Storefront, EN/DE templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/DE templates, No overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [],
            ],
            'expected' => 'de Template',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function duplicatesInLanguageChainDataProviderEnOverrides(): iterable
    {
        // EN templates
        yield 'EN Storefront, EN templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, EN templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];

        // EN/DE templates
        yield 'EN Storefront, EN/DE templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, EN/DE templates, EN overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en',
                ],
            ],
            'expected' => 'en Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function duplicatesInLanguageChainDataProviderDeOverrides(): iterable
    {
        // EN templates
        yield 'EN Storefront, EN templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];

        // EN/DE templates
        yield 'EN Storefront, EN/DE templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'en Template',
        ];
        yield 'DE Storefront, EN/DE templates, DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'de',
                ],
            ],
            'expected' => 'de Override',
        ];
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function duplicatesInLanguageChainDataProviderAllOverrides(): iterable
    {
        // EN templates
        yield 'EN Storefront, EN templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, EN templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'de Override',
        ];

        // EN/DE templates
        yield 'EN Storefront, EN/DE templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'en',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'en Override',
        ];
        yield 'DE Storefront, EN/DE templates, EN/DE overrides' => [
            'actual' => [
                'activeLanguageCode' => 'de',
                'hasTemplate' => [
                    'de',
                ],
                'hasSlotOverride' => [
                    'en', 'de',
                ],
            ],
            'expected' => 'de Override',
        ];
    }
}
