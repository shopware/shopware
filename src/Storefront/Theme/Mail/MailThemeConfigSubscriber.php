<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Mail;

use Shopware\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Exception\NoContextDataException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class MailThemeConfigSubscriber implements EventSubscriberInterface
{
    private const SALES_CHANNEL_CONTEXT = 'salesChannelContext';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly MailThemeIdLoader $mailThemeIdLoader,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MailTemplateRenderContextEvent::class => 'addSalesChannelContext',
        ];
    }

    public function addSalesChannelContext(MailTemplateRenderContextEvent $event): void
    {
        $templateData = $event->getTemplateData();

        $salesChannelId = $this->getSalesChannelId($event->getSalesChannel(), $templateData);
        if ($salesChannelId === null) {
            return;
        }

        $themeId = $this->mailThemeIdLoader->load($salesChannelId);
        if ($themeId !== null && !isset($templateData['themeId'])) {
            $event->addTemplateData('themeId', $themeId);
        }

        if (($templateData[self::SALES_CHANNEL_CONTEXT] ?? null) instanceof SalesChannelContext) {
            return;
        }

        $context = $event->getContext();

        $salesChannelContext = $this->createSalesChannelContext($salesChannelId, [
            SalesChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
            SalesChannelContextService::CURRENCY_ID => $context->getCurrencyId(),
        ]);

        if ($salesChannelContext === null) {
            return;
        }

        $event->addTemplateData(self::SALES_CHANNEL_CONTEXT, $salesChannelContext);
    }

    /**
     * @param array<string, string> $options
     */
    private function createSalesChannelContext(string $salesChannelId, array $options): ?SalesChannelContext
    {
        try {
            return $this->salesChannelContextFactory->create(
                Uuid::randomHex(),
                $salesChannelId,
                $options
            );
        } catch (NoContextDataException) {
            // Mail simulations can use generated sales-channel shells without persisted context data.
            // Rendering can continue with the existing template data in that case.
            return null;
        } catch (SalesChannelException $exception) {
            // The Admin API context carries the default currency and the admin's language,
            // which the sales channel of the mail does not have to offer.
            // Fall back to the sales channel's own default for that option.
            $unavailableOption = match ($exception->getErrorCode()) {
                SalesChannelException::CURRENCY_DOES_NOT_EXISTS_EXCEPTION => SalesChannelContextService::CURRENCY_ID,
                SalesChannelException::SALES_CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION => SalesChannelContextService::LANGUAGE_ID,
                default => null,
            };

            if ($unavailableOption === null || !\array_key_exists($unavailableOption, $options)) {
                throw $exception;
            }

            unset($options[$unavailableOption]);

            return $this->createSalesChannelContext($salesChannelId, $options);
        }
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function getSalesChannelId(?SalesChannelEntity $salesChannel, array $templateData): ?string
    {
        $salesChannelContext = $templateData[self::SALES_CHANNEL_CONTEXT] ?? null;
        if ($salesChannelContext instanceof SalesChannelContext) {
            return $salesChannelContext->getSalesChannelId();
        }

        $salesChannelId = $salesChannel?->getId();
        if ($salesChannelId !== null && Uuid::isValid($salesChannelId)) {
            return $salesChannelId;
        }

        $templateSalesChannel = $templateData['salesChannel'] ?? null;
        if ($templateSalesChannel instanceof SalesChannelEntity) {
            $salesChannelId = $templateSalesChannel->getId();
            if (Uuid::isValid($salesChannelId)) {
                return $salesChannelId;
            }
        }

        $salesChannelId = $templateData['salesChannelId'] ?? null;
        if (!\is_string($salesChannelId) || !Uuid::isValid($salesChannelId)) {
            return null;
        }

        return $salesChannelId;
    }
}
