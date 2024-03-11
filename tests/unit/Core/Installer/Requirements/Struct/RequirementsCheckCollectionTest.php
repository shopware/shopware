<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Requirements\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Installer\Requirements\Struct\PathCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Shopware\Core\Installer\Requirements\Struct\SystemCheck;

/**
 * @internal
 */
#[CoversClass(RequirementsCheckCollection::class)]
class RequirementsCheckCollectionTest extends TestCase
{
    public function testGetExpectedClass(): void
    {
        $collection = new RequirementsCheckCollection();

        $collection->add(new PathCheck('name', RequirementCheck::STATUS_SUCCESS));

        static::expectException(\InvalidArgumentException::class);
        $collection->add(new ProductEntity()); /** @phpstan-ignore-line */
    }

    /**
     * @param RequirementCheck[] $elements
     * @param RequirementCheck[] $expected
     */
    #[DataProvider('pathCheckProvider')]
    public function testGetPathChecks(array $elements, array $expected): void
    {
        $collection = new RequirementsCheckCollection($elements);

        static::assertSame($expected, array_values($collection->getPathChecks()->getElements()));
    }

    public static function pathCheckProvider(): \Generator
    {
        $pathCheck = new PathCheck('name', RequirementCheck::STATUS_SUCCESS);
        $systemCheck = new SystemCheck('name', RequirementCheck::STATUS_SUCCESS, 'required', 'installed');

        yield 'empty checks' => [
            [],
            [],
        ];

        yield 'single path check' => [
            [$pathCheck],
            [$pathCheck],
        ];

        yield 'single system check' => [
            [$systemCheck],
            [],
        ];

        yield 'system and path check' => [
            [$systemCheck, $pathCheck],
            [$pathCheck],
        ];
    }

    /**
     * @param RequirementCheck[] $elements
     * @param RequirementCheck[] $expected
     */
    #[DataProvider('systemCheckProvider')]
    public function testGetSystemChecks(array $elements, array $expected): void
    {
        $collection = new RequirementsCheckCollection($elements);

        static::assertSame($expected, array_values($collection->getSystemChecks()->getElements()));
    }

    public static function systemCheckProvider(): \Generator
    {
        $pathCheck = new PathCheck('name', RequirementCheck::STATUS_SUCCESS);
        $systemCheck = new SystemCheck('name', RequirementCheck::STATUS_SUCCESS, 'required', 'installed');

        yield 'empty checks' => [
            [],
            [],
        ];

        yield 'single path check' => [
            [$pathCheck],
            [],
        ];

        yield 'single system check' => [
            [$systemCheck],
            [$systemCheck],
        ];

        yield 'system and path check' => [
            [$systemCheck, $pathCheck],
            [$systemCheck],
        ];
    }

    /**
     * @param RequirementCheck[] $elements
     */
    #[DataProvider('errorProvider')]
    public function testHasError(array $elements, bool $expected): void
    {
        $collection = new RequirementsCheckCollection($elements);

        static::assertSame($expected, $collection->hasError());
    }

    public static function errorProvider(): \Generator
    {
        $successCheck = new PathCheck('name', RequirementCheck::STATUS_SUCCESS);
        $errorCheck = new PathCheck('name', RequirementCheck::STATUS_ERROR);
        $warningCheck = new PathCheck('name', RequirementCheck::STATUS_WARNING);

        yield 'empty checks' => [
            [],
            false,
        ];

        yield 'no error' => [
            [$successCheck, $warningCheck],
            false,
        ];

        yield 'single error' => [
            [$errorCheck],
            true,
        ];

        yield 'all states' => [
            [$successCheck, $warningCheck, $errorCheck],
            true,
        ];
    }

    /**
     * @param RequirementCheck[] $elements
     */
    #[DataProvider('pathErrorProvider')]
    public function testHasPathError(array $elements, bool $expected): void
    {
        $collection = new RequirementsCheckCollection($elements);

        static::assertSame($expected, $collection->hasPathError());
    }

    public static function pathErrorProvider(): \Generator
    {
        $successCheck = new PathCheck('name', RequirementCheck::STATUS_SUCCESS);
        $pathErrorCheck = new PathCheck('name', RequirementCheck::STATUS_ERROR);
        $systemErrorCheck = new SystemCheck('name', RequirementCheck::STATUS_ERROR, 'required', 'installed');

        yield 'empty checks' => [
            [],
            false,
        ];

        yield 'only system error' => [
            [$successCheck, $systemErrorCheck],
            false,
        ];

        yield 'only path error' => [
            [$successCheck, $pathErrorCheck],
            true,
        ];

        yield 'system and path error' => [
            [$successCheck, $pathErrorCheck, $systemErrorCheck],
            true,
        ];
    }

    /**
     * @param RequirementCheck[] $elements
     */
    #[DataProvider('systemErrorProvider')]
    public function testHasSystemError(array $elements, bool $expected): void
    {
        $collection = new RequirementsCheckCollection($elements);

        static::assertSame($expected, $collection->hasSystemError());
    }

    public static function systemErrorProvider(): \Generator
    {
        $successCheck = new PathCheck('name', RequirementCheck::STATUS_SUCCESS);
        $pathErrorCheck = new PathCheck('name', RequirementCheck::STATUS_ERROR);
        $systemErrorCheck = new SystemCheck('name', RequirementCheck::STATUS_ERROR, 'required', 'installed');

        yield 'empty checks' => [
            [],
            false,
        ];

        yield 'only path error' => [
            [$successCheck, $pathErrorCheck],
            false,
        ];

        yield 'only system error' => [
            [$successCheck, $systemErrorCheck],
            true,
        ];

        yield 'system and path error' => [
            [$successCheck, $pathErrorCheck, $systemErrorCheck],
            true,
        ];
    }
}
