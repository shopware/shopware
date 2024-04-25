<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Api\Route\ApiRouteLoader;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(ApiRouteLoader::class)]
#[CoversClass(ApiController::class)]
class ApiControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testAllowSettingNullToTranslatableFields(): void
    {
        $id = Uuid::randomHex();

        $entityName = 'product-feature-set';

        $client = $this->getBrowser();

        $client->request('POST', '/api/' . $entityName, [
            'id' => $id,
            'features' => ['test' => true],
            'name' => 'test',
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->setServerParameter('HTTP_sw-language-id', $this->getDeDeLanguageId());

        $client->request('PATCH', '/api/' . $entityName . '/' . $id, [
            'id' => $id,
            'name' => null,
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    public function testInvalidWriteInputExceptionIsConvertedToBadRequestOnCreate(): void
    {
        $entityName = 'product-feature-set';

        $client = $this->getBrowser();

        $client->request('POST', '/api/' . $entityName, [2 => 'test']);

        /** @var string $response */
        $response = $client->getResponse()->getContent();

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        static::assertEquals(Response::HTTP_BAD_REQUEST, $response['errors'][0]['status']);
        static::assertEquals('Invalid payload. Should be associative array', $response['errors'][0]['detail']);
    }

    public function testInvalidWriteInputExceptionIsConvertedToBadRequestOnUpdate(): void
    {
        $id = Uuid::randomHex();

        $entityName = 'product-feature-set';

        $client = $this->getBrowser();

        $client->request('POST', '/api/' . $entityName, [
            'id' => $id,
            'features' => ['test' => true],
            'name' => 'test',
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('PATCH', '/api/' . $entityName . '/' . $id, [2 => 'test']);

        $response = $client->getResponse();

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertEquals(Response::HTTP_BAD_REQUEST, $content['errors'][0]['status']);
        static::assertEquals('Invalid payload. Should be associative array', $content['errors'][0]['detail']);
    }

    #[DataProvider('provideEntityName')]
    public function testMustMatchEntityNameRegex(bool $match, string $entityName, string $routeName): void
    {
        $router = $this->getContainer()->get(RouterInterface::class);
        $routes = $router->getRouteCollection();

        $urlGenerator = new UrlGenerator(
            $routes,
            $router->getContext(),
        );
        $urlGenerator->setStrictRequirements(true);

        if (!$match) {
            static::expectException(InvalidParameterException::class);
            static::expectExceptionMessage('Parameter "entity" for route "' . $routeName . '" must match "[0-9a-zA-Z-]+" ("' . $entityName . '" given) to generate a corresponding URL.');
        }

        $url = $urlGenerator->generate($routeName, [
            'id' => Uuid::randomHex(),
            'entity' => $entityName,
            'versionId' => Uuid::randomHex(),
            'entityId' => Uuid::randomHex(),
        ], 0);

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
        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        $loader = new ApiRouteLoader($definitionRegistry);

        $routers = $loader->load('test');

        $apiDetail = $routers->all()['api._test_lock.detail'];
        $apiList = $routers->all()['api._test_lock.list'];

        static::assertInstanceOf(Route::class, $apiDetail);
        static::assertInstanceOf(Route::class, $apiList);

        static::assertSame('[0-9a-f]{32}(\/(extensions\/)?[0-9a-zA-Z-]+\/[0-9a-f]{32})*\/?', $apiDetail->getRequirements()['path']);
        static::assertSame('(\/[0-9a-f]{32}\/(extensions\/)?[0-9a-zA-Z-]+)*\/?', $apiList->getRequirements()['path']);
    }
}
