<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SystemConfig\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\System\System;
use Shopware\Core\System\SystemConfig\Service\AppConfigReader;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\BrokenConfigPlugin\BrokenConfigPlugin;
use Shopware\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\ValidConfigPlugin\ValidConfigPlugin;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - will be removed
 */
#[Package('framework')]
class ConfigurationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCheckConfigurationReturnsFalseForBrokenConfigXml(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // Should return false instead of throwing UtilXmlParsingException
        static::assertFalse(
            $configurationService->checkConfiguration('BrokenConfigPlugin.config', Context::createDefaultContext())
        );
    }

    public function testCheckConfigurationReturnsTrueForValidConfigXml(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $configurationService = $this->createConfigurationService([
            new ValidConfigPlugin(true, __DIR__ . '/_fixtures/ValidConfigPlugin'),
        ]);

        static::assertTrue(
            $configurationService->checkConfiguration('ValidConfigPlugin.config', Context::createDefaultContext())
        );
    }

    public function testGetConfigurationThrowsExceptionForBrokenConfigXml(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getConfiguration should still throw the exception (only checkConfiguration catches it)
        $this->expectException(UtilException::class);
        $configurationService->getConfiguration('BrokenConfigPlugin.config', Context::createDefaultContext());
    }

    public function testGetResolvedConfigurationReturnsEmptyArrayForBrokenConfigXml(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getResolvedConfiguration uses checkConfiguration, so it should return empty array
        $result = $configurationService->getResolvedConfiguration(
            'BrokenConfigPlugin.config',
            Context::createDefaultContext()
        );

        static::assertSame([], $result);
    }

    public function testBasicInformationContainsCompanyInformationCardWhenFeatureFlagIsActive(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);
        Feature::skipTestIfInActive('DOCUMENT_GENERATION_REWORK', $this);

        $configuration = $this->createConfigurationService([])->getConfiguration(
            'core.basicInformation',
            Context::createDefaultContext()
        );

        static::assertCount(1, array_filter(
            $configuration,
            static fn (array $card): bool => ($card['name'] ?? null) === 'companyInformation'
        ));
    }

    public function testBasicInformationDoesNotContainCompanyInformationCardWhenFeatureFlagIsInactive(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);
        Feature::skipTestIfActive('DOCUMENT_GENERATION_REWORK', $this);

        $configuration = $this->createConfigurationService([])->getConfiguration(
            'core.basicInformation',
            Context::createDefaultContext()
        );

        static::assertCount(0, array_filter(
            $configuration,
            static fn (array $card): bool => ($card['name'] ?? null) === 'companyInformation'
        ));
    }

    /**
     * @param list<Plugin> $plugins
     */
    private function createConfigurationService(array $plugins): ConfigurationService
    {
        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigDefinitionService = new SystemConfigDefinitionService(
            [
                new System(),
                ...$plugins,
            ],
            new ConfigReader(),
            static::getContainer()->get(AppConfigReader::class),
            static::getContainer()->get('app.repository'),
            $systemConfigService,
            static::getContainer()->get(LoggerInterface::class)
        );

        return new ConfigurationService(
            $systemConfigService,
            $systemConfigDefinitionService
        );
    }
}
