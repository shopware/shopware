<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\Mail\MailThemeConfigSubscriber;
use Shopware\Storefront\Theme\Mail\MailThemeIdLoader;

/**
 * @internal
 */
#[CoversClass(MailThemeConfigSubscriber::class)]
class MailThemeConfigSubscriberTest extends TestCase
{
    public function testAddsSalesChannelContextAndThemeIdToMailTemplateData(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $mailThemeIdLoader = $this->createMock(MailThemeIdLoader::class);
        $mailThemeIdLoader
            ->expects($this->once())
            ->method('load')
            ->with(TestDefaults::SALES_CHANNEL)
            ->willReturn($themeId);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory
            ->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static fn (string $token): bool => Uuid::isValid($token)),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
                    SalesChannelContextService::CURRENCY_ID => $context->getCurrencyId(),
                ],
            )
            ->willReturn($salesChannelContext);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);

        $event = new MailTemplateRenderContextEvent([], $context, $salesChannel);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $mailThemeIdLoader);
        $subscriber->addSalesChannelContext($event);

        static::assertSame($salesChannelContext, $event->getTemplateData()['salesChannelContext']);
        static::assertSame($themeId, $event->getTemplateData()['themeId']);
    }

    public function testKeepsTemplateDataWhenSimulatedSalesChannelHasNoContextData(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();

        $mailThemeIdLoader = $this->createMock(MailThemeIdLoader::class);
        $mailThemeIdLoader
            ->expects($this->once())
            ->method('load')
            ->with($salesChannelId)
            ->willReturn(null);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory
            ->expects($this->once())
            ->method('create')
            ->willThrowException(SalesChannelException::noContextData($salesChannelId));

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $event = new MailTemplateRenderContextEvent(['existing' => 'data'], $context, $salesChannel);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $mailThemeIdLoader);
        $subscriber->addSalesChannelContext($event);

        static::assertSame(['existing' => 'data'], $event->getTemplateData());
    }
}
