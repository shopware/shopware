<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\NamespaceFilter;

/**
 * @internal
 */
#[Package('system-settings')]
#[CoversClass(NamespaceFilter::class)]
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
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'translationKey' => '1.bas',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'translationKey' => '2.bar',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'translationKey' => '2.baz',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
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
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'translationKey' => '1.bas',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
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
                    ],
                    '1.bas' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '1.bas',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                    ],
                ],
            ],
        ];

        $result = (new NamespaceFilter())->filter($snippets, ['1']);

        static::assertSame($expected, $result);
    }

    public function testFilterMultipleNamespaces(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'translationKey' => '1.bar',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'translationKey' => '1.bas',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'translationKey' => '2.bar',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'translationKey' => '2.baz',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '3.foo' => [
                        'value' => '3_foo',
                        'translationKey' => '3.foo',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
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
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'translationKey' => '1.bas',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '3.foo' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '3.foo',
                        'author' => '',
                        'id' => null,
                        'setId' => 'firstSetId',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '3.foo' => [
                        'value' => '3_foo',
                        'translationKey' => '3.foo',
                        'origin' => '',
                        'resetTo' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '1.bar' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '1.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                    ],
                    '1.bas' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '1.bas',
                        'author' => '',
                        'id' => null,
                        'setId' => 'secondSetId',
                    ],
                ],
            ],
        ];

        $result = (new NamespaceFilter())->filter($snippets, ['1', '3']);

        static::assertSame($expected, $result);
    }
}
