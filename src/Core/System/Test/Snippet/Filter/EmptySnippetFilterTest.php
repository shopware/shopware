<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\EmptySnippetFilter;

/**
 * @internal
 */
#[Package('system-settings')]
class EmptySnippetFilterTest extends TestCase
{
    public function testGetFilterName(): void
    {
        static::assertSame('empty', (new EmptySnippetFilter())->getName());
    }

    public function testSupports(): void
    {
        static::assertTrue((new EmptySnippetFilter())->supports('empty'));
        static::assertFalse((new EmptySnippetFilter())->supports(''));
        static::assertFalse((new EmptySnippetFilter())->supports('test'));
    }

    public function testFilterOnlyEmptySnippets(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '',
                        'origin' => '',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'origin' => '1_bas',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '',
                        'origin' => '',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'origin' => '2_baz',
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '',
                        'origin' => '',
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
                        'value' => '',
                        'origin' => '',
                    ],
                ],
            ],
        ];

        $result = (new EmptySnippetFilter())->filter($snippets, true);

        static::assertEquals($expected, $result);
    }
}
