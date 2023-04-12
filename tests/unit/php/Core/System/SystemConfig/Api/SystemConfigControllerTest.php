<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SystemConfig\Api\SystemConfigController;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @package system-settings
 *
 * @internal
 *
 * @covers \Shopware\Core\System\SystemConfig\Api\SystemConfigController
 */
class SystemConfigControllerTest extends TestCase
{
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

        $requestMock = new Request();

        $contextMock = Context::createDefaultContext();

        $result = $systemConfigController->batchSaveConfiguration($requestMock, $contextMock);

        static::assertInstanceOf(JsonResponse::class, $result);
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

        $inputBag = new InputBag([
            'null' => [],
        ]);

        $requestMock = $this->createMock(Request::class);
        $requestMock->request = $inputBag;
        $requestMock->method('get')
            ->willReturn('dummy domain', [
                'null' => [],
            ]);

        $contextMock = Context::createDefaultContext();

        $this->expectException(ConstraintViolationException::class);

        $systemConfigController->batchSaveConfiguration($requestMock, $contextMock);
    }

    /**
     * @dataProvider inheritRequestDataProvider
     */
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
