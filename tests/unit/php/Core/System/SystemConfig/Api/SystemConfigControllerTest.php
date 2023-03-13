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

        $inputBag = new InputBag([
            'null' => [],
        ]);

        $requestMock = $this->createMock(Request::class);
        $requestMock->request = $inputBag;
        $requestMock->method('get')
            ->willReturn('dummy domain', [
                'null' => [],
            ]);

        $contextMock = $this->createMock(Context::class);

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

        $contextMock = $this->createMock(Context::class);

        $this->expectException(ConstraintViolationException::class);

        $systemConfigController->batchSaveConfiguration($requestMock, $contextMock);
    }
}
