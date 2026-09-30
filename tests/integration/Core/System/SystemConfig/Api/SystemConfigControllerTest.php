<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SystemConfig\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SystemConfig\Api\SystemConfigController;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class SystemConfigControllerTest extends TestCase
{
    use KernelTestBehaviour;

    public function testDeprecatedServiceIsAbsentInMajorMode(): void
    {
        static::assertSame(!Feature::isActive('v6.8.0.0'), static::getContainer()->has(ConfigurationService::class));
        static::assertInstanceOf(SystemConfigController::class, static::getContainer()->get(SystemConfigController::class));
    }

    public function testBatchSaveConfigurationPersistsNestedConfigKeys(): void
    {
        $key = 'core.basicInformation.foo.bar.baz';
        $systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $systemConfigService->delete($key);

        try {
            $response = static::getContainer()->get(SystemConfigController::class)->batchSaveConfiguration(
                new Request([], [
                    'null' => [
                        $key => 'test-value',
                    ],
                ]),
                Context::createDefaultContext()
            );

            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
            static::assertSame('test-value', $systemConfigService->get($key));
        } finally {
            $systemConfigService->delete($key);
        }
    }
}
