<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Tree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\CategoryTreePathResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CategoryTreePathResolver::class)]
#[Package('framework')]
class CategoryTreePathResolverTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('pathTestCases')]
    public function testPathResolution(
        string $activeId,
        ?string $activePath,
        string $rootId,
        ?string $rootPath,
        int $depth,
        array $expected,
    ): void {
        $result = (new CategoryTreePathResolver())->getAdditionalPathsToLoad($activeId, $activePath, $rootId, $rootPath, $depth);
        static::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, ?string, string, ?string, int, list<string>}>
     */
    public static function pathTestCases(): array
    {
        return [
            'Should return paths not loaded by depth of root' => [
                '3',
                '|1|2|',
                '1',
                '',
                1,
                ['|1|2|3|'],
            ],
            'Should skip paths within depth below root' => [
                '4',
                '|1|2|3|',
                '2',
                '|1|',
                1,
                ['|1|2|3|4|'],
            ],
            'Should handle null active path' => [
                '2',
                null,
                '1',
                '|',
                0,
                ['|2|'],
            ],
            'Should handle null root path' => [
                '3',
                '|1|2|',
                '1',
                null,
                1,
                ['|1|2|3|'],
            ],
            'Should handle empty active and root paths' => [
                '5',
                null,
                '',
                null,
                1,
                ['|5|'],
            ],
            'Should skip paths if active equals root' => [
                '2',
                '|1|',
                '2',
                '|1|',
                1,
                [],
            ],
            'Should skip paths if active and children are in depths' => [
                '2',
                '|1|',
                '1',
                '',
                2,
                [],
            ],
        ];
    }
}
