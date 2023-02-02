<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\NamespaceFilter;

/**
 * @internal
 */
#[Package('system-settings')]
class NamespaceFilterTest extends TestCase
{
    public function testGetFilterName(): void
    {
        static::assertSame('namespace', (new NamespaceFilter())->getName());
    }

    public function testSupports(): void
    {
        static::assertTrue((new NamespaceFilter())->supports('namespace'));
        static::assertFalse((new NamespaceFilter())->supports(''));
        static::assertFalse((new NamespaceFilter())->supports('test'));
    }

    public function testFilter(): void
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
                        'translationKey' => '2.baz',
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
                    '1.bas' => [
                        'value' => '1_bas',
                        'translationKey' => '1.bas',
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
                    '1.bas' => [
                        'value' => '',
                        'origin' => '',
                        'translationKey' => '1.bas',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                    ],
                ],
            ],
        ];

        $result = (new NamespaceFilter())->filter($snippets, ['1']);

        static::assertEquals($expected, $result);
    }

    public function testFilterMultipleNamespaces(): void
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
                        'translationKey' => '2.baz',
                    ],
                    '3.foo' => [
                        'value' => '3_foo',
                        'translationKey' => '3.foo',
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
                    '1.bas' => [
                        'value' => '1_bas',
                        'translationKey' => '1.bas',
                    ],
                    '3.foo' => [
                        'value' => '',
                        'origin' => '',
                        'translationKey' => '3.foo',
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
                    '1.bas' => [
                        'value' => '',
                        'origin' => '',
                        'translationKey' => '1.bas',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                    ],
                    '3.foo' => [
                        'value' => '3_foo',
                        'translationKey' => '3.foo',
                    ],
                ],
            ],
        ];

        $result = (new NamespaceFilter())->filter($snippets, ['1', '3']);

        static::assertEquals($expected, $result);
    }
}
