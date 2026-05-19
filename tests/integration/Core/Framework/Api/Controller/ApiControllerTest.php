<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Route\ApiRouteLoader;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class ApiControllerTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('provideEntityName')]
    public function testMustMatchEntityNameRegex(bool $match, string $entityName, string $routeName): void
    {
        $router = static::getContainer()->get(RouterInterface::class);
        $routes = $router->getRouteCollection();

        $urlGenerator = new UrlGenerator(
            $routes,
            $router->getContext(),
        );
        $urlGenerator->setStrictRequirements(true);

        if (!$match) {
            $this->expectExceptionObject(new InvalidParameterException('Parameter "entity" for route "' . $routeName . '" must match "[0-9a-zA-Z-]+" ("' . $entityName . '" given) to generate a corresponding URL.'));
        }

        $url = $urlGenerator->generate($routeName, [
            'id' => Uuid::randomHex(),
            'entity' => $entityName,
            'versionId' => Uuid::randomHex(),
            'entityId' => Uuid::randomHex(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        if (!$match) {
            return;
        }

        static::assertStringContainsString($entityName, $url);
    }

    public static function provideEntityName(): \Generator
    {
        yield 'not match / clone' => [false, 'named!', 'api.clone'];
        yield 'match / clone' => [true, 'named', 'api.clone'];

        yield 'not match / create version' => [false, 'named!345!@#', 'api.createVersion'];
        yield 'match / create version' => [true, 'named-123', 'api.createVersion'];

        yield 'not match / merge version' => [false, 'named@#$@8678', 'api.mergeVersion'];
        yield 'match / merge version' => [true, 'b2b-named-123', 'api.mergeVersion'];

        yield 'not match / delete version' => [false, 'named_12313', 'api.deleteVersion'];
        yield 'match / delete version' => [true, 'named-12313', 'api.deleteVersion'];
    }

    public function testLoader(): void
    {
        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        $routers = (new ApiRouteLoader($definitionRegistry))->load('test');

        $apiDetail = $routers->all()['api._test_lock.detail'];
        $apiList = $routers->all()['api._test_lock.list'];

        static::assertInstanceOf(Route::class, $apiDetail);
        static::assertInstanceOf(Route::class, $apiList);

        static::assertSame('[0-9a-f]{32}(\/(extensions\/)?[0-9a-zA-Z-]+\/[0-9a-f]{32})*\/?', $apiDetail->getRequirements()['path']);
        static::assertSame('(\/[0-9a-f]{32}\/(extensions\/)?[0-9a-zA-Z-]+)*\/?', $apiList->getRequirements()['path']);
    }
}
