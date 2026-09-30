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
use Shopware\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\BrokenConfigPlugin\BrokenConfigPlugin;
use Shopware\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\ValidConfigPlugin\ValidConfigPlugin;

/**
 * @internal
 */
#[Package('framework')]
class SystemConfigDefinitionServiceTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCheckConfigurationReturnsFalseForBrokenConfigXml(): void
    {
        $systemConfigDefinitionService = $this->createSystemConfigDefinitionService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // Should return false instead of throwing UtilXmlParsingException
        static::assertFalse(
            $systemConfigDefinitionService->checkConfiguration('BrokenConfigPlugin.config', Context::createDefaultContext())
        );
    }

    public function testCheckConfigurationReturnsTrueForValidConfigXml(): void
    {
        $systemConfigDefinitionService = $this->createSystemConfigDefinitionService([
            new ValidConfigPlugin(true, __DIR__ . '/_fixtures/ValidConfigPlugin'),
        ]);

        static::assertTrue(
            $systemConfigDefinitionService->checkConfiguration('ValidConfigPlugin.config', Context::createDefaultContext())
        );
    }

    public function testGetConfigurationThrowsExceptionForBrokenConfigXml(): void
    {
        $systemConfigDefinitionService = $this->createSystemConfigDefinitionService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getConfiguration should still throw the exception (only checkConfiguration catches it)
        $this->expectException(UtilException::class);
        $systemConfigDefinitionService->getConfiguration('BrokenConfigPlugin.config', Context::createDefaultContext());
    }

    public function testGetResolvedConfigurationReturnsEmptyArrayForBrokenConfigXml(): void
    {
        $systemConfigDefinitionService = $this->createSystemConfigDefinitionService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getResolvedConfiguration uses checkConfiguration, so it should return empty array
        $result = $systemConfigDefinitionService->getResolvedConfiguration(
            'BrokenConfigPlugin.config',
            Context::createDefaultContext()
        );

        static::assertSame([], $result);
    }

    public function testBasicInformationContainsCompanyInformationCardWhenFeatureFlagIsActive(): void
    {
        Feature::skipTestIfInActive('DOCUMENT_GENERATION_REWORK', $this);

        $configuration = $this->createSystemConfigDefinitionService([])->getConfiguration(
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

        $configuration = $this->createSystemConfigDefinitionService([])->getConfiguration(
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
    private function createSystemConfigDefinitionService(array $plugins): SystemConfigDefinitionService
    {
        return new SystemConfigDefinitionService(
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
