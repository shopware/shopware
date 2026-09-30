<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Theme\Command\ThemeChangeCommand;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeChangeCommand::class)]
class ThemeChangeCommandTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0 - will be removed together with the `--no-cleanup` option
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testItStillAcceptsTheDeprecatedNoCleanupOption(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setUniqueIdentifier($salesChannel->getId());
        $salesChannel->setName('Storefront');

        $theme = new ThemeEntity();
        $theme->setId(Uuid::randomHex());
        $theme->setUniqueIdentifier($theme->getId());
        $theme->setTechnicalName('Storefront');

        $themeService = static::createMock(ThemeService::class);
        $themeService->expects($this->once())
            ->method('assignTheme')
            ->with($theme->getId(), $salesChannel->getId(), static::anything(), false);

        $command = new ThemeChangeCommand(
            $themeService,
            static::createStub(StorefrontPluginRegistry::class),
            new StaticEntityRepository([new SalesChannelCollection([$salesChannel])]),
            new StaticEntityRepository([new ThemeCollection([$theme])])
        );

        // register the command on an application so the "question" helper set is available
        (new Application())->addCommand($command);

        $commandTester = new CommandTester($command);

        $commandTester->execute(['theme-name' => 'Storefront', '--all' => true, '--no-cleanup' => true]);
        $commandTester->assertCommandIsSuccessful();
    }
}
