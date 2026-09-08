<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\DatabaseLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DatabaseLayoutPresetLoader::class)]
class DatabaseLayoutPresetLoaderTest extends TestCase
{
    #[TestDox('returns nothing in dev, where app presets come from the filesystem')]
    public function testDevReturnsEmpty(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        $loader = new DatabaseLayoutPresetLoader(
            static::createStub(LayoutPresetSerializer::class),
            $connection,
            'dev',
            static::createStub(LoggerInterface::class),
        );

        static::assertSame([], $loader->load());
    }

    #[TestDox('denormalizes each active-app row in prod')]
    public function testProdDenormalizesRows(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'MyApp:Hero', 'schema' => '{"name":"Hero","layout":[]}', 'app_name' => 'MyApp'],
        ]);

        $serializer = static::createStub(LayoutPresetSerializer::class);
        $serializer->method('denormalize')->willReturnCallback(
            static fn (array $data, string $id): ContentSystemLayoutPresetSpecification => new ContentSystemLayoutPresetSpecification($id, 'Hero', null, null, [])
        );

        $loader = new DatabaseLayoutPresetLoader($serializer, $connection, 'prod', static::createStub(LoggerInterface::class));

        $presets = $loader->load();
        static::assertCount(1, $presets);
        static::assertSame('MyApp:Hero', $presets[0]->id);
    }

    #[TestDox('skips and logs a row whose stored data is not valid JSON')]
    public function testSkipsInvalidJson(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'MyApp:Broken', 'schema' => '{ not json', 'app_name' => 'MyApp'],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $loader = new DatabaseLayoutPresetLoader(static::createStub(LayoutPresetSerializer::class), $connection, 'prod', $logger);

        static::assertSame([], $loader->load());
    }

    #[TestDox('skips and logs a row the serializer rejects, keeping the rest')]
    public function testSkipsUndeserializableRow(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'MyApp:Bad', 'schema' => '{}', 'app_name' => 'MyApp'],
            ['name' => 'MyApp:Good', 'schema' => '{"name":"Good","layout":[]}', 'app_name' => 'MyApp'],
        ]);

        $serializer = static::createStub(LayoutPresetSerializer::class);
        $serializer->method('denormalize')->willReturnCallback(static function (array $data, string $id): ContentSystemLayoutPresetSpecification {
            if ($id === 'MyApp:Bad') {
                throw ContentSystemException::layoutPresetInvalid('missing name');
            }

            return new ContentSystemLayoutPresetSpecification($id, 'Good', null, null, []);
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $loader = new DatabaseLayoutPresetLoader($serializer, $connection, 'prod', $logger);

        $presets = $loader->load();
        static::assertCount(1, $presets);
        static::assertSame('MyApp:Good', $presets[0]->id);
    }
}
