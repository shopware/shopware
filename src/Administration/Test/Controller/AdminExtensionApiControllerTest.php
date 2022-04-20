<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdminExtensionApiController;
use Shopware\Administration\Controller\Exception\AppByNameNotFoundException;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\Response;

class AdminExtensionApiControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const EXISTING_APP_NAME = 'existingappname';
    private const NONE_EXISTING_APP_NAME = 'noneexistingappname';
    private const TARGET_URL = 'http://example.com';

    private Context $context;

    private AdminExtensionApiController $adminExtensionApiController;

    /**
     * @var Executor|MockObject
     */
    private $executor;

    protected function setUp(): void
    {
        $container = $this->getContainer();
        $appRepository = $container->get('app.repository');
        $this->executor = $this->createMock(Executor::class);
        $this->context = Context::createDefaultContext();

        $this->adminExtensionApiController = new AdminExtensionApiController(
            $this->executor,
            $container->get(ShopIdProvider::class),
            $appRepository
        );

        $appRepository->create([
            [
                'name' => self::EXISTING_APP_NAME,
                'path' => \sprintf('custom/apps/%s', self::EXISTING_APP_NAME),
                'active' => true,
                'configurable' => false,
                'version' => '0.0.1',
                'label' => 'PHPUnit',
                'appSecret' => 'PHPUnit',
                'integration' => [
                    'label' => 'PHPUnit',
                    'accessKey' => 'foo',
                    'secretAccessKey' => 'bar',
                ],
                'aclRole' => [
                    'name' => self::EXISTING_APP_NAME,
                    'privileges' => [],
                ],
            ],
        ], $this->context);
    }

    /**
     * @dataProvider providerRunAction
     */
    public function testRunAction(string $appName, ?AppAction $action = null): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_17950', $this);
        $requestDataBag = new RequestDataBag([
            'appName' => $appName,
            'entity' => 'customer',
            'action' => 'PHPUnit',
            'ids' => [],
        ]);

        if ($action !== null) {
            $requestDataBag->set('url', $action->getTargetUrl());
        }

        $appExists = $appName === self::EXISTING_APP_NAME;
        if (!$appExists) {
            $this->expectException(AppByNameNotFoundException::class);
            $this->expectExceptionMessage(\sprintf('The provided name %s is invalid and no app could be found.', $appName));
        } else {
            $this->executor->expects(static::once())->method('execute')->with(static::callback(static function (AppAction $action) {
                return $action->getTargetUrl() === 'http://example.com';
            }))->willReturn(new Response());
        }

        $response = $this->adminExtensionApiController->runAction($requestDataBag, $this->context);

        if ($appExists) {
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        }
    }

    public function providerRunAction(): array
    {
        $action = new AppAction(
            self::TARGET_URL,
            self::TARGET_URL,
            '0.0.1',
            'customer',
            'PHPUnit',
            [],
            'foo',
            'bar',
            Uuid::randomHex()
        );

        return [
            [
                self::NONE_EXISTING_APP_NAME,
            ],
            [
                self::EXISTING_APP_NAME,
                $action,
            ],
        ];
    }
}
