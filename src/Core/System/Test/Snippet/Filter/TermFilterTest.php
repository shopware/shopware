<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\TermFilter;

/**
 * @internal
 */
#[Package('system-settings')]
class TermFilterTest extends TestCase
{
    public function testGetFilterName(): void
    {
        static::assertSame('term', (new TermFilter())->getName());
    }

    public function testSupports(): void
    {
        static::assertTrue((new TermFilter())->supports('term'));
        static::assertFalse((new TermFilter())->supports(''));
        static::assertFalse((new TermFilter())->supports('test'));
    }

    public function testFilterWithValueMatch(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'translationKey' => '1.bar',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'translationKey' => '1.bas',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'translationKey' => '2.bar',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'translationKey' => '2.bas',
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'translationKey' => '1.bar',
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
                        'translationKey' => '2.bar',
                    ],
                ],
            ],
        ];

        $result = (new TermFilter())->filter($snippets, '_bar');

        static::assertEquals($expected, $result);
    }

    public function testFilterWithKeyMatch(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'translationKey' => '1.bar',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'translationKey' => '1.bas',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'translationKey' => '2.bar',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'translationKey' => '2.bas',
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'translationKey' => '1.bar',
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
                        'translationKey' => '2.bar',
                    ],
                ],
            ],
        ];

        $result = (new TermFilter())->filter($snippets, '.bar');

        static::assertEquals($expected, $result);
    }

    public function testFilterDoesntRemoveSnippetInOtherSet(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'translationKey' => '1.bar',
                    ],
                    '1.baz' => [
                        'value' => '1_baz',
                        'translationKey' => '1.baz',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'translationKey' => '2.bar',
                    ],
                    '1.baz' => [
                        'value' => '2_baz',
                        'translationKey' => '1.baz',
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.baz' => [
                        'value' => '1_baz',
                        'translationKey' => '1.baz',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '1.baz' => [
                        'value' => '2_baz',
                        'translationKey' => '1.baz',
                    ],
                ],
            ],
        ];

        $result = (new TermFilter())->filter($snippets, '1_baz');

        static::assertEquals($expected, $result);
    }
}
