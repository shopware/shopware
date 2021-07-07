<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Acl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclAnnotationValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Test\Api\Acl\fixtures\AclTestController;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class AclAnnotationValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepositoryInterface $appRepository;

    private Connection $connection;

    private AclAnnotationValidator $validator;

    public function setUp(): void
    {
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->validator = new AclAnnotationValidator($this->connection);
    }

    /**
     * @dataProvider annotationProvider
     */
    public function testValidateRequest(array $privileges, array $acl, bool $pass): void
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
        $request->attributes->set('_acl', new Acl(['value' => $acl]));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $kernel = $this->createMock(Kernel::class);

        $exception = null;

        try {
            $this->validator->validate(new ControllerEvent($kernel, [new AclTestController(), 'testRoute'], $request, 1));
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
        $request->attributes->set('_acl', new Acl(['value' => ['app']]));
        $request->attributes->set('id', $actionId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $kernel = $this->createMock(Kernel::class);

        $exception = null;

        try {
            $this->validator->validate(new ControllerEvent($kernel, [new AclTestController(), 'testRoute'], $request, 1));
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
        $request->attributes->set('_acl', new Acl(['value' => ['app']]));
        $request->attributes->set('id', $actionId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $kernel = $this->createMock(Kernel::class);

        $exception = null;

        try {
            $this->validator->validate(new ControllerEvent($kernel, [new AclTestController(), 'testRoute'], $request, 1));
        } catch (\Exception $e) {
            $exception = $e;
        }

        static::assertInstanceOf(MissingPrivilegeException::class, $exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
    }

    public function annotationProvider()
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

    private function registerActionButton($appName, $actionId): void
    {
        $this->appRepository->create([[
            'name' => $appName,
            'active' => true,
            'path' => __DIR__ . '/../../App/Manifest/_fixtures/test',
            'iconRaw' => file_get_contents(__DIR__ . '/../../App/Manifest/_fixtures/test/icon.png'),
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
                'writeAccess' => false,
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => $appName,
            ],
        ]], Context::createDefaultContext());
    }
}
