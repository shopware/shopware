<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\AuthorFilter;

/**
 * @internal
 */
#[Package('system-settings')]
class AuthorFilterTest extends TestCase
{
    public function testGetFilterName(): void
    {
        static::assertSame('author', (new AuthorFilter())->getName());
    }

    public function testSupports(): void
    {
        static::assertTrue((new AuthorFilter())->supports('author'));
        static::assertFalse((new AuthorFilter())->supports(''));
        static::assertFalse((new AuthorFilter())->supports('test'));
    }

    public function testFilter(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'author' => 'Shopware',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'author' => 'Anonymous',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'author' => 'Shopware',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'author' => 'Anonymous',
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'author' => 'Shopware',
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
                        'author' => 'Shopware',
                    ],
                ],
            ],
        ];

        $result = (new AuthorFilter())->filter($snippets, ['Shopware']);

        static::assertEquals($expected, $result);
    }

    public function testFilterDoesntRemoveSnippetInOtherSet(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '1_bar',
                        'author' => 'Shopware',
                    ],
                    'foo.baz' => [
                        'value' => '1_baz',
                        'author' => 'Shopware',
                    ],
                    'foo.bas' => [
                        'value' => '1_bas',
                        'author' => 'Anonymous',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '2_bar',
                        'author' => 'Shopware',
                    ],
                    'foo.baz' => [
                        'value' => '2_baz',
                        'author' => 'Anonymous',
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '1_bar',
                        'author' => 'Shopware',
                    ],
                    'foo.baz' => [
                        'value' => '1_baz',
                        'author' => 'Shopware',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '2_bar',
                        'author' => 'Shopware',
                    ],
                    'foo.baz' => [
                        'value' => '2_baz',
                        'author' => 'Anonymous',
                    ],
                ],
            ],
        ];

        $result = (new AuthorFilter())->filter($snippets, ['Shopware']);

        static::assertEquals($expected, $result);
    }

    public function testFilterWithMultipleAuthors(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '1_bar',
                        'author' => 'Test',
                    ],
                    'foo.baz' => [
                        'value' => '1_baz',
                        'author' => 'Shopware',
                    ],
                    'foo.bas' => [
                        'value' => '1_bas',
                        'author' => 'Anonymous',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '2_bar',
                        'author' => 'Test',
                    ],
                    'foo.baz' => [
                        'value' => '2_baz',
                        'author' => 'Anonymous',
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '1_bar',
                        'author' => 'Test',
                    ],
                    'foo.baz' => [
                        'value' => '1_baz',
                        'author' => 'Shopware',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    'foo.bar' => [
                        'value' => '2_bar',
                        'author' => 'Test',
                    ],
                    'foo.baz' => [
                        'value' => '2_baz',
                        'author' => 'Anonymous',
                    ],
                ],
            ],
        ];

        $result = (new AuthorFilter())->filter($snippets, ['Shopware', 'Test']);

        static::assertEquals($expected, $result);
    }
}
