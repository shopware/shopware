<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SystemConfig\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\System\System;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigCard;
use Shopware\Core\System\SystemConfig\DTO\SystemConfigTab;
use Shopware\Core\System\SystemConfig\Service\AppConfigReader;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\BrokenConfigPlugin\BrokenConfigPlugin;
use Shopware\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\ValidConfigPlugin\ValidConfigPlugin;

/**
 * @internal
 */
#[Package('framework')]
class ConfigurationServiceTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCheckConfigurationReturnsFalseForBrokenConfigXml(): void
    {
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

    public function testGetSystemConfigDefinitionThrowsExceptionForBrokenConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getSystemConfigDefinition should still throw the exception (only checkConfiguration catches it)
        $this->expectException(UtilException::class);
        $configurationService->getSystemConfigDefinition('BrokenConfigPlugin.config', Context::createDefaultContext());
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

    public function testGetResolvedSystemConfigDefinitionReturnsEmptyArrayForBrokenConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getResolvedSystemConfigDefinition uses checkConfiguration, so it should return empty array
        $result = $configurationService->getResolvedSystemConfigDefinition(
            'BrokenConfigPlugin.config',
            Context::createDefaultContext()
        );

        static::assertSame([], $result);
    }

    public function testBasicInformationContainsCompanyInformationCardWhenFeatureFlagIsActive(): void
    {
        Feature::skipTestIfInActive('DOCUMENT_GENERATION_REWORK', $this);

        $configuration = $this->createConfigurationService([])->getSystemConfigDefinition(
            'core.basicInformation',
            Context::createDefaultContext()
        );

        static::assertInstanceOf(SystemConfigTab::class, $configuration[0]);
        static::assertCount(1, array_filter(
            $configuration[0]->cards,
            static fn (SystemConfigCard $card): bool => $card->name === 'companyInformation'
        ));
    }

    public function testBasicInformationDoesNotContainCompanyInformationCardWhenFeatureFlagIsInactive(): void
    {
        Feature::skipTestIfActive('DOCUMENT_GENERATION_REWORK', $this);

        $configuration = $this->createConfigurationService([])->getSystemConfigDefinition(
            'core.basicInformation',
            Context::createDefaultContext()
        );

        static::assertInstanceOf(SystemConfigTab::class, $configuration[0]);
        static::assertCount(0, array_filter(
            $configuration[0]->cards,
            static fn (SystemConfigCard $card): bool => $card->name === 'companyInformation'
        ));
    }

    /**
     * @param list<Plugin> $plugins
     */
    private function createConfigurationService(array $plugins): ConfigurationService
    {
        return new ConfigurationService(
            [
                new System(),
                ...$plugins,
            ],
            new ConfigReader(),
            static::getContainer()->get(AppConfigReader::class),
            static::getContainer()->get('app.repository'),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(LoggerInterface::class)
        );
    }
}
