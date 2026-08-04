<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Shopware\Administration\Controller\AdminExtensionApiController;
use Shopware\Administration\Controller\Exception\AppByNameNotFoundException;
use Shopware\Administration\Controller\Exception\MissingAppSecretException;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminExtensionApiController::class)]
class AdminExtensionApiControllerTest extends TestCase
{
    private MockObject&AppPayloadServiceHelper $appPayloadServiceHelper;

    private Context $context;

    /**
     * @var MockObject&EntityRepository<AppCollection>
     */
    private MockObject&EntityRepository $entityRepository;

    private MockObject&Executor $executor;

    private MockObject&QuerySigner $querySigner;

    private AdminExtensionApiController $controller;

    protected function setUp(): void
    {
        $this->appPayloadServiceHelper = $this->createMock(AppPayloadServiceHelper::class);
        $this->context = Context::createDefaultContext();
        $this->querySigner = $this->createMock(QuerySigner::class);
        $this->executor = $this->createMock(Executor::class);
        $this->entityRepository = $this->createMock(EntityRepository::class);

        $this->controller = new AdminExtensionApiController(
            $this->executor,
            $this->appPayloadServiceHelper,
            $this->entityRepository,
            $this->querySigner
        );
    }

    public function testRunActionThrowsAppByNameNotFoundExceptionWhenAppIsNotFound(): void
    {
        if (!Feature::isActive('v6.7.0.0')) {
            $this->expectExceptionObject(new AppByNameNotFoundException('test-app'));
        } else {
            $this->expectException(AppNotFoundException::class);
        }

        $this->controller->runAction(new RequestDataBag(['appName' => 'test-app']), $this->context);
    }

    public function testRunActionThrowsMissingRequestParameterWhenAppNameIsMissing(): void
    {
        $this->expectExceptionObject(AppException::missingRequestParameter('appName'));

        $this->controller->runAction(new RequestDataBag(), $this->context);
    }

    public function testRunActionThrowsAppByNameNotFoundExceptionWhenAppSecretIsNull(): void
    {
        if (!Feature::isActive('v6.7.0.0')) {
            $this->expectException(MissingAppSecretException::class);
            $this->expectExceptionMessage('Failed to retrieve app secret.');
        } else {
            $this->expectException(AppException::class);
            $this->expectExceptionMessage(AppException::appSecretMissing('test-app')->getMessage());
        }

        $entity = $this->buildAppEntity('test-app', null, []);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->controller->runAction(new RequestDataBag(['appName' => $entity->getName()]), $this->context);
    }

    public function testRunActionThrowsMissingRequestParameterWhenUrlIsMissing(): void
    {
        $this->expectExceptionObject(AppException::missingRequestParameter('url'));

        $entity = $this->buildAppEntity('test-app', 'test-secrets', []);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->controller->runAction(new RequestDataBag(['appName' => $entity->getName()]), $this->context);
    }

    public function testRunActionThrowsInvalidArgumentExceptionWhenUrlIsNotValid(): void
    {
        $this->expectExceptionObject(AppException::invalidArgument('test-host is not a valid url'));

        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['shopware']);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->controller->runAction(
            new RequestDataBag(['appName' => $entity->getName(), 'url' => 'test-host']),
            $this->context
        );
    }

    public function testRunActionThrowsUnallowedHostExceptionWhenTargetHostIsNotAllowed(): void
    {
        $this->expectExceptionObject(AppException::hostNotAllowed('https://not-allowed.example', 'test-app'));

        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['shopware']);
        $entityRepository = $this->assertEntityRepositoryWithEntity($entity);

        $this->buildController(entityRepository: $entityRepository)->runAction(
            new RequestDataBag(['appName' => $entity->getName(), 'url' => 'https://not-allowed.example']),
            $this->context
        );
    }

    public function testRunActionThrowsInvalidArgumentExceptionWhenNoIdInRequestBag(): void
    {
        if (!Feature::isActive('v6.7.0.0')) {
            $this->expectExceptionObject(new \InvalidArgumentException('Ids must be an array'));
        } else {
            $this->expectException(AppException::class);
            $this->expectExceptionMessage(AppException::invalidArgument('Ids must be an array')->getMessage());
        }

        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['foo.bar']);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->controller->runAction(
            new RequestDataBag(['appName' => $entity->getName(), 'url' => 'https://foo.bar/test']),
            $this->context
        );
    }

    public function testRunActionExecutesAnAppAction(): void
    {
        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['foo.bar']);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->appPayloadServiceHelper->expects($this->once())->method('buildSource')->with('1.0.0', $entity->getName());
        $this->executor->expects($this->once())->method('execute');

        $this->controller->runAction(
            new RequestDataBag([
                'appName' => $entity->getName(),
                'url' => 'https://foo.bar',
                'ids' => [Uuid::randomHex()],
                'entity' => 'app',
                'action' => 'do-nothing',
            ]),
            $this->context,
        );
    }

    public function testRunActionAllowsUserWithAppSpecificPrivilege(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['app.test-app']);

        $this->assertRunActionExecutesWith($source);
    }

    public function testRunActionAllowsUserWithAppAllPrivilege(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['app.all']);

        $this->assertRunActionExecutesWith($source);
    }

    public function testRunActionThrowsMissingPrivilegeWhenUserLacksAppPrivilege(): void
    {
        $this->expectExceptionObject(ApiException::missingPrivileges(['app.test-app']));

        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['product:read']);
        $context = Context::createDefaultContext($source);

        $this->executor->expects($this->never())->method('execute');

        $this->controller->runAction(
            new RequestDataBag(['appName' => 'test-app']),
            $context
        );
    }

    public function testSignUriThrowsAppByNameNotFoundExceptionWhenAppIsNotFound(): void
    {
        if (!Feature::isActive('v6.7.0.0')) {
            $this->expectExceptionObject(new AppByNameNotFoundException('test-app'));
        } else {
            $this->expectException(AppNotFoundException::class);
        }

        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['app.test-app']);

        $this->controller->signUri(
            new RequestDataBag(['appName' => 'test-app']),
            Context::createDefaultContext($source)
        );
    }

    public function testSignUriThrowsMissingPrivilegeWhenUserLacksAppPrivilege(): void
    {
        $this->expectExceptionObject(ApiException::missingPrivileges(['app.test-app']));

        $querySigner = $this->createMock(QuerySigner::class);
        $querySigner->expects($this->never())->method('signUri');
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['product:read']);

        $this->buildController(querySigner: $querySigner)->signUri(
            new RequestDataBag(['appName' => 'test-app']),
            Context::createDefaultContext($source)
        );
    }

    public function testSignUriThrowsMissingRequestParameterWhenAppNameIsMissing(): void
    {
        $this->expectExceptionObject(AppException::missingRequestParameter('appName'));

        $this->controller->signUri(new RequestDataBag(), $this->context);
    }

    public function testSignUriThrowsMissingRequestParameterWhenUriIsMissing(): void
    {
        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['foo.bar']);
        $entityRepository = $this->assertEntityRepositoryWithEntity($entity);
        $querySigner = $this->createMock(QuerySigner::class);
        $querySigner->expects($this->never())->method('signUri');
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['app.test-app']);

        $this->expectExceptionObject(AppException::missingRequestParameter('uri'));

        $this->buildController(querySigner: $querySigner, entityRepository: $entityRepository)->signUri(
            new RequestDataBag(['appName' => 'test-app']),
            Context::createDefaultContext($source)
        );
    }

    public function testSignUriThrowsInvalidArgumentWhenUriIsNotValid(): void
    {
        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['foo.bar']);
        $entityRepository = $this->assertEntityRepositoryWithEntity($entity);
        $querySigner = $this->createMock(QuerySigner::class);
        $querySigner->expects($this->never())->method('signUri');
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['app.test-app']);

        $this->expectExceptionObject(AppException::invalidArgument('not-a-url is not a valid url'));

        $this->buildController(querySigner: $querySigner, entityRepository: $entityRepository)->signUri(
            new RequestDataBag([
                'appName' => 'test-app',
                'uri' => 'not-a-url',
            ]),
            Context::createDefaultContext($source)
        );
    }

    public function testSignUriThrowsHostNotAllowedWhenUriHostIsNotAllowed(): void
    {
        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['allowed.example']);
        $entityRepository = $this->assertEntityRepositoryWithEntity($entity);
        $querySigner = $this->createMock(QuerySigner::class);
        $querySigner->expects($this->never())->method('signUri');
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['app.test-app']);

        $this->expectExceptionObject(AppException::hostNotAllowed('https://evil.example/action', 'test-app'));

        $this->buildController(querySigner: $querySigner, entityRepository: $entityRepository)->signUri(
            new RequestDataBag([
                'appName' => 'test-app',
                'uri' => 'https://evil.example/action',
            ]),
            Context::createDefaultContext($source)
        );
    }

    public function testSignUriReturnsJsonResponseWithUri(): void
    {
        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['foo.bar']);
        $entityRepository = $this->assertEntityRepositoryWithEntity($entity);
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['app.all']);
        $context = Context::createDefaultContext($source);

        $requestBag = new RequestDataBag(['appName' => $entity->getName(), 'uri' => 'https://foo.bar/test-uri']);

        $querySigner = $this->createMock(QuerySigner::class);
        $querySigner->expects($this->once())->method('signUri')
            ->with($requestBag->get('uri'), $entity, $context)
            ->willReturn(static::createStub(UriInterface::class));

        $response = $this->buildController(querySigner: $querySigner, entityRepository: $entityRepository)->signUri($requestBag, $context);

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"uri":""}', $response->getContent());
    }

    protected function assertEntityRepositoryWithEntity(AppEntity $entity): void
    {
        $collection = new EntityCollection();
        $collection->add($entity);
        $collection->add($this->buildAppEntity('secondAppDiscarded', null, []));

        $this->entityRepository->expects($this->once())->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'app',
                    2,
                    $collection,
                    null,
                    new Criteria(),
                    $this->context
                )
            );
    }

    /**
     * @param list<string>|null $allowedHosts
     */
    protected function buildAppEntity(string $name, ?string $appSecret, ?array $allowedHosts): AppEntity
    {
        $entity = new AppEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setUniqueIdentifier(Uuid::randomHex());
        $entity->setName($name);
        $entity->setVersion('1.0.0');
        $entity->setAppSecret($appSecret);
        $entity->setAllowedHosts($allowedHosts);

        return $entity;
    }

    private function assertRunActionExecutesWith(AdminApiSource $source): void
    {
        $entity = $this->buildAppEntity('test-app', 'test-secrets', ['foo.bar']);
        $this->assertEntityRepositoryWithEntity($entity);

        $this->executor->expects($this->once())->method('execute');

        $this->controller->runAction(
            new RequestDataBag([
                'appName' => $entity->getName(),
                'url' => 'https://foo.bar',
                'ids' => [Uuid::randomHex()],
                'entity' => 'app',
                'action' => 'do-nothing',
            ]),
            Context::createDefaultContext($source),
        );
    }

    private function buildController(
        ?Executor $executor = null,
        ?AppPayloadServiceHelper $appPayloadServiceHelper = null,
        ?EntityRepository $entityRepository = null,
        ?QuerySigner $querySigner = null,
    ): AdminExtensionApiController {
        return new AdminExtensionApiController(
            $executor ?? $this->executor,
            $appPayloadServiceHelper ?? $this->appPayloadServiceHelper,
            $entityRepository ?? $this->entityRepository,
            $querySigner ?? $this->querySigner,
        );
    }
}
