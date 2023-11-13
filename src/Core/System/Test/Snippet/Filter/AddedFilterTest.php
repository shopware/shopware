<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Snippet\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\AddedFilter;

/**
 * @internal
 */
#[Package('system-settings')]
class AddedFilterTest extends TestCase
{
    public function testGetFilterName(): void
    {
        static::assertSame('added', (new AddedFilter())->getName());
    }

    public function testSupports(): void
    {
        static::assertTrue((new AddedFilter())->supports('added'));
        static::assertFalse((new AddedFilter())->supports(''));
        static::assertFalse((new AddedFilter())->supports('test'));
    }

    public function testFilterOnlyCustomSnippets(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'author' => 'user/admin',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'author' => 'shopware',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'author' => 'user/admin',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'author' => 'shopware',
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'author' => 'user/admin',
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
                        'author' => 'user/admin',
                    ],
                ],
            ],
        ];

        $result = (new AddedFilter())->filter($snippets, true);

        static::assertEquals($expected, $result);
    }
}
