<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\AddedFilter;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(AddedFilter::class)]
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
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'author' => 'user/admin',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'id' => null,
                        'setId' => '',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
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
                        'author' => 'user/admin',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
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
                        'value' => '2_bar',
                        'author' => 'user/admin',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
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

        $result = (new AddedFilter())->filter($snippets, true);

        static::assertSame($expected, $result);
    }
}
