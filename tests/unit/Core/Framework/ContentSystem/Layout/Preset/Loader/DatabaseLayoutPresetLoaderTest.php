<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\DatabaseLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSpecificationSerializer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

        static::assertSame([], $this->loader($connection, 'dev', static::createStub(LoggerInterface::class))->load());
    }

    #[TestDox('builds a compiled specification from each active-app row in prod')]
    public function testProdBuildsSpecsFromRows(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'MyApp:Hero', 'schema' => '{"name":"Hero","description":"A hero.","icon":"regular-star","layout":[]}', 'app_name' => 'MyApp'],
        ]);

        $presets = $this->loader($connection, 'prod', static::createStub(LoggerInterface::class))->load();

        static::assertCount(1, $presets);
        static::assertSame('MyApp:Hero', $presets[0]->id);
        static::assertSame('Hero', $presets[0]->name);
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

        static::assertSame([], $this->loader($connection, 'prod', $logger)->load());
    }

    #[TestDox('skips and logs a row that fails validation, keeping the rest')]
    public function testSkipsInvalidRow(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['name' => 'MyApp:Bad', 'schema' => '{"layout":[]}', 'app_name' => 'MyApp'],
            ['name' => 'MyApp:Good', 'schema' => '{"name":"Good","description":"Good preset.","icon":"regular-star","layout":[]}', 'app_name' => 'MyApp'],
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $presets = $this->loader($connection, 'prod', $logger)->load();

        static::assertCount(1, $presets);
        static::assertSame('MyApp:Good', $presets[0]->id);
    }

    private function loader(Connection $connection, string $environment, LoggerInterface $logger): DatabaseLayoutPresetLoader
    {
        return new DatabaseLayoutPresetLoader(
            new LayoutPresetSpecificationSerializer(),
            static::createStub(LayoutPresetPayloadCompiler::class),
            $this->validator(),
            $connection,
            $environment,
            $logger,
        );
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
