<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\EmptySnippetFilter;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(EmptySnippetFilter::class)]
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
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'origin' => '1_bas',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'origin' => '2_baz',
                        'resetTo' => '',
                        'translationKey' => '',
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
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'author' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '2.bar' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '2.bar',
                        'author' => '',
                        'id' => null,
                        'setId' => 'firstSetId',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
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
                ],
            ],
        ];

        $result = (new EmptySnippetFilter())->filter($snippets, true);

        static::assertSame($expected, $result);
    }
}
