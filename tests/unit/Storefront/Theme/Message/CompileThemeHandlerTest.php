<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\ConfigLoader\AbstractConfigLoader;
use Shopware\Storefront\Theme\Message\CompileThemeHandler;
use Shopware\Storefront\Theme\Message\CompileThemeMessage;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCompiler;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CompileThemeHandler::class)]
class CompileThemeHandlerTest extends TestCase
{
    public function testHandleMessageCompile(): void
    {
        $themeCompilerMock = $this->createMock(ThemeCompiler::class);
        $notificationServiceMock = static::createStub(NotificationService::class);
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $message = new CompileThemeMessage(TestDefaults::SALES_CHANNEL, $themeId, true, $context);

        $themeCompilerMock->expects($this->once())->method('compileTheme');

        $scEntity = new SalesChannelEntity();
        $scEntity->setUniqueIdentifier(Uuid::randomHex());
        $scEntity->setName('Test SalesChannel');

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRep */
        $salesChannelRep = new StaticEntityRepository([new EntityCollection([$scEntity])]);

        $handler = new CompileThemeHandler(
            $themeCompilerMock,
            static::createStub(AbstractConfigLoader::class),
            static::createStub(StorefrontPluginRegistry::class),
            $notificationServiceMock,
            $salesChannelRep,
            static::createStub(ThemeRuntimeConfigService::class),
        );

        $handler($message);
    }
}
