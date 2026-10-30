<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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
    #[TestWith(['prod'])]
    #[TestWith(['test'])]
    #[TestWith(['staging'])]
    #[TestDox('builds a validated dto from each active-app row in the $environment environment')]
    public function testNonDevBuildsDtosFromRows(string $environment): void
    {
        $dtos = $this->loader($this->connectionWithRows([
            ['name' => 'MyApp:Hero', 'schema' => '{"name":"Hero","description":"A hero.","icon":"regular-star","category":"content","priority":-100,"illustration":"preset-media-and-text","layout":[{"component":"text"}]}', 'app_name' => 'MyApp'],
        ]), $environment)->load();

        static::assertSame(['MyApp:Hero'], array_keys($dtos));
        static::assertSame('Hero', $dtos['MyApp:Hero']->name);
        static::assertSame('A hero.', $dtos['MyApp:Hero']->description);
        static::assertSame('regular-star', $dtos['MyApp:Hero']->icon);
        static::assertSame('content', $dtos['MyApp:Hero']->category);
        static::assertSame(-100, $dtos['MyApp:Hero']->priority);
        static::assertSame('preset-media-and-text', $dtos['MyApp:Hero']->illustration);
        static::assertSame([['component' => 'text']], $dtos['MyApp:Hero']->layout);
    }

    #[TestDox('returns the dtos of all rows in row order')]
    public function testLoadsEveryRowInOrder(): void
    {
        $dtos = $this->loader($this->connectionWithRows([
            ['name' => 'MyApp:First', 'schema' => '{"name":"First","description":"First preset.","icon":"regular-star","layout":[]}', 'app_name' => 'MyApp'],
            ['name' => 'MyApp:Second', 'schema' => '{"name":"Second","description":"Second preset.","icon":"regular-star","layout":[]}', 'app_name' => 'MyApp'],
        ]), 'prod')->load();

        static::assertSame(['MyApp:First', 'MyApp:Second'], array_keys($dtos));
        static::assertNull($dtos['MyApp:First']->category);
        static::assertSame(0, $dtos['MyApp:First']->priority);
        static::assertNull($dtos['MyApp:First']->illustration);
    }

    #[TestDox('aborts the load on a row whose stored data is valid JSON but not an array')]
    public function testNonArrayDataAbortsLoad(): void
    {
        $loader = $this->loader($this->connectionWithRows([
            ['name' => 'MyApp:Scalar', 'schema' => '"just a string"', 'app_name' => 'MyApp'],
        ]), 'prod');

        try {
            $loader->load();
            static::fail('Expected the load to abort.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESET_LOAD_FAILED, $e->getErrorCode());
            static::assertSame('Failed to load layout preset from "app:MyApp:MyApp:Scalar": Persisted data must decode to an array/map, got string', $e->getMessage());
        }
    }

    #[TestDox('returns nothing in dev, where app presets come from the filesystem')]
    public function testDevReturnsEmpty(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        static::assertSame([], $this->loader($connection, 'dev')->load());
    }

    #[TestDox('names an unnamed row "<unknown>" in the load failure')]
    public function testUnnamedRowFailureNamesItUnknown(): void
    {
        $loader = $this->loader($this->connectionWithRows([
            ['name' => '', 'schema' => '{ not json', 'app_name' => 'MyApp'],
        ]), 'prod');

        try {
            $loader->load();
            static::fail('Expected the load to abort.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESET_LOAD_FAILED, $e->getErrorCode());
            static::assertSame('Failed to load layout preset from "app:MyApp:<unknown>": Invalid JSON data: Syntax error', $e->getMessage());
        }
    }

    #[TestDox('aborts the load on a row whose stored data is not valid JSON')]
    public function testInvalidJsonAbortsLoad(): void
    {
        $loader = $this->loader($this->connectionWithRows([
            ['name' => 'MyApp:Broken', 'schema' => '{ not json', 'app_name' => 'MyApp'],
        ]), 'prod');

        try {
            $loader->load();
            static::fail('Expected the load to abort.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESET_LOAD_FAILED, $e->getErrorCode());
            static::assertSame('Failed to load layout preset from "app:MyApp:MyApp:Broken": Invalid JSON data: Syntax error', $e->getMessage());
            static::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    #[TestDox('aborts the load on a row that fails validation, without keeping the rest')]
    public function testInvalidRowAbortsLoad(): void
    {
        $loader = $this->loader($this->connectionWithRows([
            ['name' => 'MyApp:Bad', 'schema' => '{"layout":[]}', 'app_name' => 'MyApp'],
            ['name' => 'MyApp:Good', 'schema' => '{"name":"Good","description":"Good preset.","icon":"regular-star","layout":[]}', 'app_name' => 'MyApp'],
        ]), 'prod');

        try {
            $loader->load();
            static::fail('Expected the load to abort.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESETS_INVALID, $e->getErrorCode());
            static::assertStringContainsString('Layout preset validation failed: ', $e->getMessage());
            static::assertStringContainsString('MyApp:Bad', $e->getMessage());
            static::assertStringNotContainsString('MyApp:Good', $e->getMessage());
        }
    }

    #[TestDox('aborts the load on an invalid row that follows a valid one, without naming the valid one')]
    public function testInvalidRowAfterValidRowAbortsLoad(): void
    {
        $loader = $this->loader($this->connectionWithRows([
            ['name' => 'MyApp:Good', 'schema' => '{"name":"Good","description":"Good preset.","icon":"regular-star","layout":[]}', 'app_name' => 'MyApp'],
            ['name' => 'MyApp:Bad', 'schema' => '{"layout":[]}', 'app_name' => 'MyApp'],
        ]), 'prod');

        try {
            $loader->load();
            static::fail('Expected the load to abort.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESETS_INVALID, $e->getErrorCode());
            static::assertStringContainsString('MyApp:Bad', $e->getMessage());
            static::assertStringNotContainsString('MyApp:Good', $e->getMessage());
        }
    }

    /**
     * @param list<array{name: string, schema: string, app_name: string}> $rows
     */
    private function connectionWithRows(array $rows): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return $connection;
    }

    private function loader(Connection $connection, string $environment): DatabaseLayoutPresetLoader
    {
        return new DatabaseLayoutPresetLoader(
            new LayoutPresetSpecificationSerializer(),
            $this->validator(),
            $connection,
            $environment,
        );
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
