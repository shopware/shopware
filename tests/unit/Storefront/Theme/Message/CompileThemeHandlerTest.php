<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Notification\NotificationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\ConfigLoader\AbstractConfigLoader;
use Shopware\Storefront\Theme\Message\CompileThemeHandler;
use Shopware\Storefront\Theme\Message\CompileThemeMessage;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeCompiler;

/**
 * @internal
 */
#[CoversClass(CompileThemeHandler::class)]
class CompileThemeHandlerTest extends TestCase
{
    public function testHandleMessageCompile(): void
    {
        $themeCompilerMock = $this->createMock(ThemeCompiler::class);
        $notificationServiceMock = $this->createMock(NotificationService::class);
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $message = new CompileThemeMessage(TestDefaults::SALES_CHANNEL, $themeId, true, $context);

        $themeCompilerMock->expects(static::once())->method('compileTheme');

        $scEntity = new SalesChannelEntity();
        $scEntity->setUniqueIdentifier(Uuid::randomHex());
        $scEntity->setName('Test SalesChannel');

        /** @var StaticEntityRepository<EntityCollection<SalesChannelEntity>> $salesChannelRep */
        $salesChannelRep = new StaticEntityRepository([new EntityCollection([$scEntity])]);

        $handler = new CompileThemeHandler(
            $themeCompilerMock,
            $this->createMock(AbstractConfigLoader::class),
            $this->createMock(StorefrontPluginRegistryInterface::class),
            $notificationServiceMock,
            $salesChannelRep
        );

        $handler($message);
    }
}
