<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Acl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclAnnotationValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\Api\Acl\fixtures\AclTestController;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use function print_r;

/**
 * @internal
 */
class AclAnnotationValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $appRepository;

    private Connection $connection;

    private AclAnnotationValidator $validator;

    protected function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->validator = new AclAnnotationValidator($this->connection);
    }

    /**
     * @dataProvider annotationProvider
     *
     * @param list<string> $privileges
     * @param list<string> $acl
     */
    public function testValidateRequestAsRouteAttribute(array $privileges, array $acl, bool $pass): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($privileges);

        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ACL, $acl);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $kernel = $this->createMock(Kernel::class);

        $exception = null;

        $controller = new AclTestController();

        try {
            $this->validator->validate(new ControllerEvent($kernel, $controller->testRoute(...), $request, 1));
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($pass) {
            static::assertNull($exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
        } else {
            static::assertInstanceOf(MissingPrivilegeException::class, $exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
        }
    }

    public function testValidateAppRequest(): void
    {
        $actionId = Uuid::randomHex();
        $appName = 'AppSuccess';
        $this->registerActionButton($appName, $actionId);

        $source = new AdminApiSource(null, null);
        $source->setPermissions(['app.' . $appName]);
        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ACL, ['app']);
        $request->attributes->set('id', $actionId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $kernel = $this->createMock(Kernel::class);

        $exception = null;

        $controller = new AclTestController();

        try {
            $this->validator->validate(new ControllerEvent($kernel, $controller->testRoute(...), $request, 1));
        } catch (\Exception $e) {
            $exception = $e;
        }

        static::assertNull($exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
    }

    public function testValidateAppRequestFail(): void
    {
        $actionId = Uuid::randomHex();
        $appName = 'AppFail';
        $this->registerActionButton($appName, $actionId);

        $source = new AdminApiSource(null, null);
        $source->setPermissions(['app.fail']);
        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ACL, ['app']);
        $request->attributes->set('id', $actionId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $kernel = $this->createMock(Kernel::class);

        $exception = null;

        $controller = new AclTestController();

        try {
            $this->validator->validate(new ControllerEvent($kernel, $controller->testRoute(...), $request, 1));
        } catch (\Exception $e) {
            $exception = $e;
        }

        static::assertInstanceOf(MissingPrivilegeException::class, $exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
    }

    /**
     * @return list<array{0: list<string>, 1: list<string>, 2: bool}>
     */
    public static function annotationProvider(): array
    {
        return [
            [
                // privs of user   //acl   // should pass?
                ['product:write'], [], true,
            ],
            [
                [], ['productWrite'], false,
            ],
            [
                ['product:write'], ['product:write'], true,
            ],
            [
                ['product:write', 'product:read'], ['product:write', 'product:read'], true,
            ],
            [
                ['product:write'], ['product:write', 'product:read'], false,
            ],
            [
                ['api.test.route'], ['api.test.route'], true,
            ],
            [
                [], ['api.test.route'], false,
            ],
            [
                ['product:write', 'product:read'], ['api.test.route'], false,
            ],
            [
                ['app.all'], ['app'], true,
            ],
        ];
    }

    private function registerActionButton(string $appName, string $actionId): void
    {
        $iconRaw = \file_get_contents(__DIR__ . '/../../../../../../tests/integration/php/Core/Framework/App/Manifest/_fixtures/test/icon.png');
        static::assertNotFalse($iconRaw);

        $this->appRepository->create([[
            'name' => $appName,
            'active' => true,
            'path' => __DIR__ . '/../../App/Manifest/_fixtures/test',
            'iconRaw' => $iconRaw,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'actionButtons' => [
                [
                    'id' => $actionId,
                    'entity' => 'order',
                    'view' => 'detail',
                    'action' => 'orderDetailApp1',
                    'label' => 'Order Detail App1',
                    'url' => 'app1.com/order/detail',
                ],
            ],
            'integration' => [
                'label' => $appName,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => $appName,
            ],
        ]], Context::createDefaultContext());
    }
}
