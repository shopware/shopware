<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Requirements\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Requirements\Struct\PathCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementCheck;
use Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Shopware\Core\Installer\Requirements\Struct\SystemCheck;

/**
 * @internal
 *
 * @covers \Shopware\Core\Installer\Requirements\Struct\RequirementsCheckCollection
 */
class RequirementsCheckCollectionTest extends TestCase
{
    public function testGetExpectedClass(): void
    {
        $collection = new RequirementsCheckCollection();

        static::assertSame(RequirementCheck::class, $collection->getExpectedClass());
    }

    /**
     * @param RequirementCheck[] $elements
     * @param RequirementCheck[] $expected
     *
     * @dataProvider pathCheckProvider
     */
    public function testGetPathChecks(array $elements, array $expected): void
    {
        $collection = new RequirementsCheckCollection($elements);

        static::assertSame($expected, array_values($collection->getPathChecks()->getElements()));
    }

    public function pathCheckProvider(): \Generator
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
     *
     * @dataProvider systemCheckProvider
     */
    public function testGetSystemChecks(array $elements, array $expected): void
    {
        $collection = new RequirementsCheckCollection($elements);

        static::assertSame($expected, array_values($collection->getSystemChecks()->getElements()));
    }

    public function systemCheckProvider(): \Generator
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
     *
     * @dataProvider errorProvider
     */
    public function testHasError(array $elements, bool $expected): void
    {
        $collection = new RequirementsCheckCollection($elements);

        static::assertSame($expected, $collection->hasError());
    }

    public function errorProvider(): \Generator
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
     *
     * @dataProvider pathErrorProvider
     */
    public function testHasPathError(array $elements, bool $expected): void
    {
        $collection = new RequirementsCheckCollection($elements);

        static::assertSame($expected, $collection->hasPathError());
    }

    public function pathErrorProvider(): \Generator
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
     *
     * @dataProvider systemErrorProvider
     */
    public function testHasSystemError(array $elements, bool $expected): void
    {
        $collection = new RequirementsCheckCollection($elements);

        static::assertSame($expected, $collection->hasSystemError());
    }

    public function systemErrorProvider(): \Generator
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
