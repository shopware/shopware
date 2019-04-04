<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Filter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Snippet\Filter\CustomFilter;

class CustomFilterTest extends TestCase
{
    public function testGetFilterName()
    {
        static::assertSame('custom', (new CustomFilter())->getName());
    }

    public function testSupports()
    {
        static::assertTrue((new CustomFilter())->supports('custom'));
        static::assertFalse((new CustomFilter())->supports(''));
        static::assertFalse((new CustomFilter())->supports('test'));
    }

    public function testFilterOnlyCustomSnippets()
    {
        $snippets = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'id' => 1,
                    ],
                    '1.bas' => [
                        'value' => '1_bas',
                        'id' => null,
                    ],
                ],
            ],
            'secondSetId' => [
                'snippets' => [
                    '2.bar' => [
                        'value' => '2_bar',
                        'id' => 2,
                    ],
                    '2.baz' => [
                        'value' => '2_baz',
                        'id' => null,
                    ],
                ],
            ],
        ];

        $expected = [
            'firstSetId' => [
                'snippets' => [
                    '1.bar' => [
                        'value' => '1_bar',
                        'id' => 1,
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
                        'id' => 2,
                    ],
                ],
            ],
        ];

        $result = (new CustomFilter())->filter($snippets, true);

        static::assertEquals($expected, $result);
    }
}
