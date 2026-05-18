<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\Subscriber\MailThemeConfigSubscriber;

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

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
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

        $event = new MailBeforeValidateEvent([
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'languageId' => Uuid::randomHex(),
        ], $context);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $connection);
        $subscriber->addSalesChannelContext($event);

        static::assertSame($salesChannelContext, $event->getTemplateData()['salesChannelContext']);
        static::assertSame($themeId, $event->getTemplateData()['themeId']);
    }
}
