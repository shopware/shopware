<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Service\SupportedFeaturesService;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Shopware\Tests\Unit\Common\Stubs\IniMock;

/**
 * @internal
 */
#[CoversClass(SupportedFeaturesService::class)]
class SupportedFeaturesServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        IniMock::register(MemorySizeCalculator::class);
    }

    /**
     * @param iterable<string> $entities
     * @param iterable<string> $fileTypes
     * @param class-string<\Throwable>|null $expectedException
     */
    #[DataProvider('constructDataProvider')]
    public function testConstruct(
        iterable $entities,
        iterable $fileTypes,
        ?string $expectedException = null,
        ?string $expectedExceptionMessage = null
    ): void {
        if ($expectedException && $expectedExceptionMessage) {
            $this->expectException($expectedException);
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        new SupportedFeaturesService($entities, $fileTypes);

        $this->expectNotToPerformAssertions();
    }

    public static function constructDataProvider(): \Generator
    {
        yield 'entities: int - file types: string' => [
            'entities' => [1],
            'fileTypes' => ['bar'],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported entities should be collection of strings. integer given.',
        ];

        yield 'entities: string - file types: int' => [
            'entities' => ['foo'],
            'fileTypes' => [1],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported file types should be collection of strings. integer given',
        ];

        yield 'entities: int - file types: int' => [
            'entities' => [1],
            'fileTypes' => [1],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported entities should be collection of strings. integer given.',
        ];

        yield 'entities: array - file types: string' => [
            'entities' => [[]],
            'fileTypes' => ['bar'],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported entities should be collection of strings. array given.',
        ];

        yield 'entities: string - file types: array' => [
            'entities' => ['foo'],
            'fileTypes' => [[]],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported file types should be collection of strings. array given',
        ];

        yield 'entities: object - file types: string' => [
            'entities' => [new \stdClass()],
            'fileTypes' => ['bar'],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported entities should be collection of strings. object given.',
        ];

        yield 'entities: string - file types: object' => [
            'entities' => ['foo'],
            'fileTypes' => [new \stdClass()],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported file types should be collection of strings. object given',
        ];

        yield 'entities: double - file types: string' => [
            'entities' => [1.1],
            'fileTypes' => ['bar'],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported entities should be collection of strings. double given.',
        ];

        yield 'entities: string - file types: double' => [
            'entities' => ['foo'],
            'fileTypes' => [1.1],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported file types should be collection of strings. double given',
        ];

        yield 'entities: bool - file types: string' => [
            'entities' => [true],
            'fileTypes' => ['bar'],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported entities should be collection of strings. boolean given.',
        ];

        yield 'entities: string - file types: bool' => [
            'entities' => ['foo'],
            'fileTypes' => [true],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported file types should be collection of strings. boolean given',
        ];

        yield 'entities: null - file types: string' => [
            'entities' => [null],
            'fileTypes' => ['bar'],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported entities should be collection of strings. NULL given.',
        ];

        yield 'entities: string - file types: null' => [
            'entities' => ['foo'],
            'fileTypes' => [null],
            'expectedException' => \InvalidArgumentException::class,
            'expectedExceptionMessage' => 'Supported file types should be collection of strings. NULL given',
        ];

        yield 'entities: string - file types: string' => [
            'entities' => ['foo'],
            'fileTypes' => ['bar'],
        ];

        yield 'entities: empty - file types: empty' => [
            'entities' => [],
            'fileTypes' => [],
        ];

        yield 'entities: empty - file types: string' => [
            'entities' => [],
            'fileTypes' => ['bar'],
        ];

        yield 'entities: string - file types: empty' => [
            'entities' => ['foo'],
            'fileTypes' => [],
        ];
    }

    public function testGetEntities(): void
    {
        $entities = ['foo', 'bar'];

        $supportedFeaturesService = new SupportedFeaturesService($entities, []);

        static::assertEquals($entities, $supportedFeaturesService->getEntities());
    }

    public function testGetFileTypes(): void
    {
        $fileTypes = ['foo', 'bar'];

        $supportedFeaturesService = new SupportedFeaturesService([], $fileTypes);

        static::assertEquals($fileTypes, $supportedFeaturesService->getFileTypes());
    }

    public function testGetUploadFileSizeLimit(): void
    {
        IniMock::withIniMock([
            'upload_max_filesize' => '4G',
            'post_max_size' => '4G',
        ]);

        $supportedFeaturesService = new SupportedFeaturesService([], []);

        static::assertEquals(2 * 1024 * 1024 * 1024, $supportedFeaturesService->getUploadFileSizeLimit());

        IniMock::withIniMock([]);
    }
}
