<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\TranslationKeyFilter;

/**
 * @internal
 */
#[Package('system-settings')]
class TranslationKeyFilterTest extends TestCase
{
    public function testGetFilterName(): void
    {
        static::assertSame('translationKey', (new TranslationKeyFilter())->getName());
    }

    public function testSupports(): void
    {
        static::assertTrue((new TranslationKeyFilter())->supports('translationKey'));
        static::assertFalse((new TranslationKeyFilter())->supports(''));
        static::assertFalse((new TranslationKeyFilter())->supports('test'));
    }

    public function testFilter(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '',
                        'origin' => '',
                        'translationKey' => '1.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                    ],
                ],
            ],
        ];

        $result = (new TranslationKeyFilter())->filter($snippets, ['1.bar']);

        static::assertEquals($expected, $result);
    }

    public function testFilterMultipleTranslationKeys(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                    ],
                    '2.bar' => [
                        'value' => '',
                        'origin' => '',
                        'translationKey' => '2.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'firstSetId',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '',
                        'origin' => '',
                        'translationKey' => '1.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                    ],
                    '2.bar' => [
                        'value' => '2_bar',
                    ],
                ],
            ],
        ];

        $result = (new TranslationKeyFilter())->filter($snippets, ['1.bar', '2.bar']);

        static::assertEquals($expected, $result);
    }
}
