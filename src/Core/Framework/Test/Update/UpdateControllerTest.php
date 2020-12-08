<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Update\Api\UpdateController;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Update\Event\UpdatePreFinishEvent;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Shopware\Core\Framework\Update\Services\PluginCompatibility;
use Shopware\Core\Framework\Update\Services\RequirementsValidator;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class UpdateControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testEventDispatcherNotCalledOnInvalidToken(): void
    {
        $context = Context::createDefaultContext();
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->method('get')
            ->willReturnMap([
                [UpdateController::UPDATE_TOKEN_KEY, null, 'valid_token'],
            ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $updateController = new UpdateController(
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->getContainer()->get(ApiClient::class),
            $this->getContainer()->get(RequirementsValidator::class),
            $this->getContainer()->get(PluginCompatibility::class),
            $eventDispatcher,
            $systemConfigService,
            $this->getContainer()->get(PluginLifecycleService::class),
            $this->getContainer()->get('user.repository'),
            $this->getContainer()->getParameter('kernel.shopware_version')
        );
        $updateController->setContainer($this->getContainer());

        $eventDispatcher->expects(static::never())->method('dispatch');

        $request = new Request();
        $request->query->set('offset', 0);

        $response = $updateController->finish('', $request, $context);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/admin', $response->headers->get('location'));

        $response = $updateController->finish('invalid token', $request, $context);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/admin', $response->headers->get('location'));
    }

    public function testFinishDispatchesEvents(): void
    {
        static::markTestSkipped('Should be fixed with NEXT-8271');

        $token = 'test_token';
        $context = Context::createDefaultContext();

        $previousVersion = '6.0.0_test';
        $version = '6.0.1_test';

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->method('get')
            ->willReturnMap([
                [UpdateController::UPDATE_TOKEN_KEY, null, $token],
                [UpdateController::UPDATE_PREVIOUS_VERSION_KEY, null, $previousVersion],
            ]);

        $containerWithoutPlugins = $this->createMock(Container::class);
        $containerWithPlugins = $this->createMock(Container::class);

        $eventDispatcherWithoutPlugins = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcherWithPlugins = $this->createMock(EventDispatcherInterface::class);

        $pluginLoader = $this->createMock(StaticKernelPluginLoader::class);
        $pluginLoader->method('getClassLoader')->willReturn(KernelLifecycleManager::getClassLoader());

        $kernel = $this->createMock(Kernel::class);
        $kernel->method('getPluginLoader')->willReturn($pluginLoader);

        $containerWithoutPlugins->method('get')->willReturnMap([
            ['event_dispatcher', 1, $eventDispatcherWithoutPlugins],
            ['kernel', 1, $kernel],
            [Connection::class, 1, $this->createMock(Connection::class)],
            ['router', 1, $this->getContainer()->get('router')],
        ]);
        $containerWithPlugins->method('get')->willReturnMap([
            ['event_dispatcher', 1, $eventDispatcherWithPlugins],
            ['kernel', 1, $kernel],
            [Connection::class, 1, $this->createMock(Connection::class)],
            ['router', 1, $this->getContainer()->get('router')],
        ]);

        $kernel->method('getContainer')->willReturn($containerWithPlugins);

        $updateController = new UpdateController(
            $this->getContainer()->getParameter('kernel.project_dir'),
            $this->getContainer()->get(ApiClient::class),
            $this->getContainer()->get(RequirementsValidator::class),
            $this->getContainer()->get(PluginCompatibility::class),
            $eventDispatcherWithoutPlugins,
            $systemConfigService,
            $this->getContainer()->get(PluginLifecycleService::class),
            $this->getContainer()->get('user.repository'),
            $version
        );
        $updateController->setContainer($containerWithoutPlugins);

        // dispatched without plugins
        $eventDispatcherWithoutPlugins
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(static function (UpdatePreFinishEvent $subject) use ($previousVersion, $version) {
                static::assertInstanceOf(UpdatePreFinishEvent::class, $subject);
                static::assertSame($previousVersion, $subject->getOldVersion());
                static::assertSame($version, $subject->getNewVersion());

                return true;
            }));

        // reboots with DbalKernelPluginLoader
        $kernel->expects(static::once())
            ->method('reboot')
            ->with(
                static::anything(),
                static::callback(static function ($pluginLoader) {
                    static::assertInstanceOf(DbalKernelPluginLoader::class, $pluginLoader);

                    return true;
                })
            );

        // dispatched with plugins enabled
        $eventDispatcherWithPlugins
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(static function (UpdatePostFinishEvent $subject) use ($previousVersion, $version) {
                static::assertInstanceOf(UpdatePostFinishEvent::class, $subject);
                static::assertSame($previousVersion, $subject->getOldVersion());
                static::assertSame($version, $subject->getNewVersion());

                return true;
            }));

        $stack = $this->getContainer()->get(RequestStack::class);
        $prop = ReflectionHelper::getProperty(RequestStack::class, 'requests');
        $prop->setValue($stack, []);

        // fake request
        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, 'en-GB');

        $stack->push($request);

        $request = new Request();
        $request->query->set('offset', 0);

        $response = $updateController->finish($token, $request, $context);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/admin', $response->headers->get('location'));
    }

    public function testAutoUpdateReplacesItSelf(): void
    {
        $rootDir = $this->getContainer()->getParameter('kernel.project_dir');
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->method('get')
            ->willReturnMap([
                [UpdateController::UPDATE_TOKEN_KEY, null, 'valid_token'],
            ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $updateController = new UpdateController(
            $rootDir,
            $this->getContainer()->get(ApiClient::class),
            $this->getContainer()->get(RequirementsValidator::class),
            $this->getContainer()->get(PluginCompatibility::class),
            $eventDispatcher,
            $systemConfigService,
            $this->getContainer()->get(PluginLifecycleService::class),
            $this->getContainer()->get('user.repository'),
            $this->getContainer()->getParameter('kernel.shopware_version')
        );
        $updateController->setContainer($this->getContainer());

        $replaceRecoveryFiles = \Closure::bind(function (UpdateController $controller, string $dir): void {
            $controller->replaceRecoveryFiles($dir);
        }, null, $updateController);

        // Prepare update folder
        $fs = new Filesystem();
        $recoveryPath = '/vendor/shopware/recovery/';
        $tmpDir = sys_get_temp_dir() . '/' . uniqid(__METHOD__, true);
        $tmpRecoveryPath = $tmpDir . $recoveryPath;

        $fs->mkdir($tmpRecoveryPath);
        $fs->dumpFile($tmpRecoveryPath . '/test.txt', 'B');

        // Prepare old system
        $fs->mkdir($rootDir . $recoveryPath);
        $fs->dumpFile($rootDir . $recoveryPath . '/test.txt', 'A');

        $replaceRecoveryFiles($updateController, $tmpDir);

        static::assertStringEqualsFile($rootDir . $recoveryPath . '/test.txt', 'B');
        $fs->remove($tmpDir);
        $fs->remove($rootDir . $recoveryPath);
    }

    public function testCheckUpdateEndpoint(): void
    {
        $this->getBrowser()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/_action/update/check');
        /** @var JsonResponse $response */
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($response->getContent(), true));
    }

    public function testCheckUpdateEndpointNoPermissions(): void
    {
        try {
            $this->authorizeBrowser($this->getBrowser(), [], ['something']);
            $_SERVER['SHOPWARE_UPDATE_TEST'] = 1;
            $this->getBrowser()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/_action/update/check');
            $_SERVER['SHOPWARE_UPDATE_TEST'] = 0;

            /** @var JsonResponse $response */
            $response = $this->getBrowser()->getResponse();

            static::assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
            static::assertEquals(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($response->getContent(), true)['errors'][0]['code'], $response->getContent());
        } finally {
            $this->resetBrowser();
        }
    }
}
