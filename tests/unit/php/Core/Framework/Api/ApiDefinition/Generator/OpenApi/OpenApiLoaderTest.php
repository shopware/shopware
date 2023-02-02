<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi;

use OpenApi\Annotations\Components;
use OpenApi\Annotations\PathItem;
use OpenApi\Generator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\Event\OpenApiPathsEvent;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiLoader
 *
 * @internal
 */
class OpenApiLoaderTest extends TestCase
{
    public function testLoadWithoutExternalControllersCreatesEmptySchema(): void
    {
        $apiLoader = new OpenApiLoader(
            $this->createMock(EventDispatcherInterface::class)
        );
        $schema = $apiLoader->load(DefinitionService::API);
        /** @var string|PathItem[] $paths */
        $paths = $schema->paths;
        /** @var string|Components $components */
        $components = $schema->components;

        static::assertSame(Generator::UNDEFINED, $paths);
        static::assertSame(Generator::UNDEFINED, $components);
    }

    public function testLoadPathsWithExternalControllers(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(OpenApiPathsEvent::class, function (OpenApiPathsEvent $event): void {
            $event->addPath(__DIR__ . '/_fixtures/StoreApiTestOtherRoute.php');
        });
        $apiLoader = new OpenApiLoader(
            $eventDispatcher
        );
        $schema = $apiLoader->load(DefinitionService::API);
        /** @var string|Components $components */
        $components = $schema->components;

        static::assertCount(1, $schema->paths);
        static::assertSame('/test', $schema->paths[0]->path);
        static::assertSame(Generator::UNDEFINED, $components);
    }
}
