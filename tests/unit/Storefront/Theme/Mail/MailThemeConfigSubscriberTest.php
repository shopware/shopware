<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
#[Package('discovery')]
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

    public function testFallsBackToSalesChannelCurrencyWhenContextCurrencyIsNotAvailable(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $requestedOptions = [];

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory
            ->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(static function (string $token, string $salesChannelId, array $options) use (&$requestedOptions, $context, $salesChannelContext) {
                $requestedOptions[] = $options;
                if (\array_key_exists(SalesChannelContextService::CURRENCY_ID, $options)) {
                    throw SalesChannelException::currencyNotFound($context->getCurrencyId());
                }

                return $salesChannelContext;
            });

        $event = $this->createEvent($context);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $this->createMailThemeIdLoader());
        $subscriber->addSalesChannelContext($event);

        static::assertSame($salesChannelContext, $event->getTemplateData()['salesChannelContext']);
        static::assertSame([
            [
                SalesChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
                SalesChannelContextService::CURRENCY_ID => $context->getCurrencyId(),
            ],
            [
                SalesChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
            ],
        ], $requestedOptions);
    }

    public function testFallsBackToSalesChannelLanguageWhenContextLanguageIsNotAvailable(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $requestedOptions = [];

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory
            ->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(static function (string $token, string $salesChannelId, array $options) use (&$requestedOptions, $context, $salesChannelContext) {
                $requestedOptions[] = $options;
                if (\array_key_exists(SalesChannelContextService::LANGUAGE_ID, $options)) {
                    throw SalesChannelException::providedLanguageNotAvailable($context->getLanguageId(), [Uuid::randomHex()]);
                }

                return $salesChannelContext;
            });

        $event = $this->createEvent($context);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $this->createMailThemeIdLoader());
        $subscriber->addSalesChannelContext($event);

        static::assertSame($salesChannelContext, $event->getTemplateData()['salesChannelContext']);
        static::assertSame([
            [
                SalesChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
                SalesChannelContextService::CURRENCY_ID => $context->getCurrencyId(),
            ],
            [
                SalesChannelContextService::CURRENCY_ID => $context->getCurrencyId(),
            ],
        ], $requestedOptions);
    }

    public function testFallsBackToSalesChannelDefaultsWhenNeitherContextCurrencyNorLanguageIsAvailable(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $requestedOptions = [];

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory
            ->expects($this->exactly(3))
            ->method('create')
            ->willReturnCallback(static function (string $token, string $salesChannelId, array $options) use (&$requestedOptions, $context, $salesChannelContext) {
                $requestedOptions[] = $options;
                if (\array_key_exists(SalesChannelContextService::CURRENCY_ID, $options)) {
                    throw SalesChannelException::currencyNotFound($context->getCurrencyId());
                }
                if (\array_key_exists(SalesChannelContextService::LANGUAGE_ID, $options)) {
                    throw SalesChannelException::providedLanguageNotAvailable($context->getLanguageId(), [Uuid::randomHex()]);
                }

                return $salesChannelContext;
            });

        $event = $this->createEvent($context);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $this->createMailThemeIdLoader());
        $subscriber->addSalesChannelContext($event);

        static::assertSame($salesChannelContext, $event->getTemplateData()['salesChannelContext']);
        static::assertSame([], $requestedOptions[2]);
    }

    public function testRethrowsWhenSalesChannelDefaultCurrencyIsNotAvailable(): void
    {
        $context = Context::createDefaultContext();
        $exception = SalesChannelException::currencyNotFound(Uuid::randomHex());

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory
            ->expects($this->exactly(2))
            ->method('create')
            ->willThrowException($exception);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $this->createMailThemeIdLoader());

        $this->expectExceptionObject($exception);

        $subscriber->addSalesChannelContext($this->createEvent($context));
    }

    private function createEvent(Context $context): MailTemplateRenderContextEvent
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);

        return new MailTemplateRenderContextEvent([], $context, $salesChannel);
    }

    private function createMailThemeIdLoader(): MailThemeIdLoader
    {
        $mailThemeIdLoader = static::createStub(MailThemeIdLoader::class);
        $mailThemeIdLoader->method('load')->willReturn(null);

        return $mailThemeIdLoader;
    }
}
