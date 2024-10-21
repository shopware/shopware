<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Filter\EditedFilter;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(EditedFilter::class)]
class EditedFilterTest extends TestCase
{
    public function testGetFilterName(): void
    {
        static::assertSame('edited', (new EditedFilter())->getName());
    }

    public function testSupports(): void
    {
        static::assertTrue((new EditedFilter())->supports('edited'));
        static::assertFalse((new EditedFilter())->supports(''));
        static::assertFalse((new EditedFilter())->supports('test'));
    }

    public function testFilterOnlyCustomSnippets(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'id' => '1',
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'id' => null,
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'id' => '2',
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'id' => null,
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
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
                        'id' => '1',
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
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
                        'id' => '2',
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
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

        $result = (new EditedFilter())->filter($snippets, true);

        static::assertSame($expected, $result);
    }

    public function testFilterDoesntIncludeAddedSnippets(): void
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'id' => '1',
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'id' => null,
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'id' => '2',
                        'author' => 'user/admin',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
                        'setId' => '',
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'id' => null,
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
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
                        'id' => '1',
                        'author' => 'shopware',
                        'origin' => '',
                        'resetTo' => '',
                        'translationKey' => '',
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
                ],
            ],
        ];

        $result = (new EditedFilter())->filter($snippets, true);

        static::assertSame($expected, $result);
    }
}
