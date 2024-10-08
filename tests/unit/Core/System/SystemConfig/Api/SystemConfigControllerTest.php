<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SystemConfig\Api\SystemConfigController;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SystemConfigController::class)]
class SystemConfigControllerTest extends TestCase
{
    public function testCheckConfigurationEmptyDomain(): void
    {
        $controller = new SystemConfigController(
            $this->createMock(ConfigurationService::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(SystemConfigValidator::class)
        );

        $request = new Request();

        $context = Context::createDefaultContext();

        $result = $controller->checkConfiguration($request, $context);

        static::assertSame('false', $result->getContent());
    }

    public function testCheckConfiguration(): void
    {
        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService
            ->method('checkConfiguration')
            ->willReturn(true);

        $controller = new SystemConfigController(
            $configurationService,
            $this->createMock(SystemConfigService::class),
            $this->createMock(SystemConfigValidator::class)
        );

        $request = new Request();
        $request->query->set('domain', 'foo');

        $context = Context::createDefaultContext();

        $result = $controller->checkConfiguration($request, $context);

        static::assertSame('true', $result->getContent());
    }

    public function testGetConfiguration(): void
    {
        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService
            ->method('getConfiguration')
            ->willReturn(['foo' => 'bar']);

        $controller = new SystemConfigController(
            $configurationService,
            $this->createMock(SystemConfigService::class),
            $this->createMock(SystemConfigValidator::class)
        );

        $request = new Request();
        $request->query->set('domain', 'foo');

        $context = Context::createDefaultContext();

        $result = $controller->getConfiguration($request, $context);

        static::assertSame('{"foo":"bar"}', $result->getContent());
    }

    public function testGetConfigurationWithName(): void
    {
        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService
            ->method('getConfiguration')
            ->willReturn(['foo' => 'bar']);

        $controller = new SystemConfigController(
            $configurationService,
            $this->createMock(SystemConfigService::class),
            $this->createMock(SystemConfigValidator::class)
        );

        $request = new Request();
        $request->query->set('domain', '');

        $context = Context::createDefaultContext();

        static::expectException(RoutingException::class);
        static::expectExceptionMessage('Parameter "domain" is missing.');
        $controller->getConfiguration($request, $context);
    }

    public function testGetConfigurationValues(): void
    {
        $controller = new SystemConfigController(
            $this->createMock(ConfigurationService::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(SystemConfigValidator::class)
        );

        $request = new Request();
        $request->query->set('domain', '');

        static::expectException(RoutingException::class);
        static::expectExceptionMessage('Parameter "domain" is missing.');
        $controller->getConfigurationValues($request);
    }

    public function testGetConfigurationValuesEmptyArray(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig
            ->method('getDomain')
            ->with('foo')
            ->willReturn([]);

        $controller = new SystemConfigController(
            $this->createMock(ConfigurationService::class),
            $systemConfig,
            $this->createMock(SystemConfigValidator::class)
        );

        $request = new Request();
        $request->query->set('domain', 'foo');

        $data = $controller->getConfigurationValues($request);

        static::assertSame('{}', $data->getContent());
    }

    public function testGetConfigurationValuesArray(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig
            ->method('getDomain')
            ->with('foo')
            ->willReturn(['foo' => 'bar']);

        $controller = new SystemConfigController(
            $this->createMock(ConfigurationService::class),
            $systemConfig,
            $this->createMock(SystemConfigValidator::class)
        );

        $request = new Request();
        $request->query->set('domain', 'foo');

        $data = $controller->getConfigurationValues($request);

        static::assertSame('{"foo":"bar"}', $data->getContent());
    }

    public function testSaveConfig(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig
            ->method('setMultiple')
            ->with([
                'foo' => '1',
            ]);

        $controller = new SystemConfigController(
            $this->createMock(ConfigurationService::class),
            $systemConfig,
            $this->createMock(SystemConfigValidator::class)
        );

        $request = new Request();
        $request->request->set('foo', '1');

        $data = $controller->saveConfiguration($request);

        static::assertSame(Response::HTTP_NO_CONTENT, $data->getStatusCode());
    }

    public function testBatchSaveConfigurationSuccess(): void
    {
        $configurationServiceMock = $this->createMock(ConfigurationService::class);

        $systemConfigServiceMock = $this->createMock(SystemConfigService::class);

        $systemConfigValidatorMock = $this->createMock(SystemConfigValidator::class);
        $systemConfigValidatorMock->method('validate');

        $systemConfigController = new SystemConfigController(
            $configurationServiceMock,
            $systemConfigServiceMock,
            $systemConfigValidatorMock
        );

        $request = new Request();
        $request->request->set('null', []);

        $context = Context::createDefaultContext();

        $result = $systemConfigController->batchSaveConfiguration($request, $context);

        static::assertSame('{}', $result->getContent());
    }

    public function testBatchSaveConfigurationFailure(): void
    {
        $configurationServiceMock = $this->createMock(ConfigurationService::class);

        $systemConfigServiceMock = $this->createMock(SystemConfigService::class);

        $systemConfigValidatorMock = $this->createMock(SystemConfigValidator::class);
        $systemConfigValidatorMock->method('validate')
            ->willThrowException($this->createMock(ConstraintViolationException::class));

        $systemConfigController = new SystemConfigController(
            $configurationServiceMock,
            $systemConfigServiceMock,
            $systemConfigValidatorMock
        );

        $request = new Request();
        $request->request->set('null', []);

        $context = Context::createDefaultContext();

        $this->expectException(ConstraintViolationException::class);

        $systemConfigController->batchSaveConfiguration($request, $context);
    }

    #[DataProvider('inheritRequestDataProvider')]
    public function testInheritFlag(Request $request, bool $expectedFlag): void
    {
        $systemConfigService = static::createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('getDomain')
            ->with('dummy domain', 'dummy sales channel', $expectedFlag);

        $systemConfigController = new SystemConfigController(
            static::createMock(ConfigurationService::class),
            $systemConfigService,
            static::createMock(SystemConfigValidator::class)
        );

        $systemConfigController->getConfigurationValues($request);
    }

    public static function inheritRequestDataProvider(): \Generator
    {
        yield 'inherit flag not set' => [
            new Request([
                'domain' => 'dummy domain',
                'salesChannelId' => 'dummy sales channel',
            ]),
            false,
        ];

        yield 'inherit flag set to false' => [
            new Request([
                'domain' => 'dummy domain',
                'salesChannelId' => 'dummy sales channel',
                'inherit' => false,
            ]),
            false,
        ];

        yield 'inherit flag set to true' => [
            new Request([
                'domain' => 'dummy domain',
                'salesChannelId' => 'dummy sales channel',
                'inherit' => true,
            ]),
            true,
        ];
    }
}
