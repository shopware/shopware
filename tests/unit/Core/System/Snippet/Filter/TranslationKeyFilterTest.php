<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\TranslationKeyFilter;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationKeyFilter::class)]
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
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '1.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $result = (new TranslationKeyFilter())->filter($snippets, ['1.bar']);

        static::assertSame($expected, $result);
    }

    public function testFilterMultipleTranslationKeys(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '2.bar' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '2.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'firstSetId',
                        'hasFileValue' => false,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                        'hasFileValue' => false,
                    ],
                    '1.bar' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '1.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                        'hasFileValue' => false,
                    ],
                ],
            ],
        ];

        $result = (new TranslationKeyFilter())->filter($snippets, ['1.bar', '2.bar']);

        static::assertSame($expected, $result);
    }
}
