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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
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

        $options = [
            SalesChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
            SalesChannelContextService::CURRENCY_ID => $context->getCurrencyId(),
        ];

        try {
            $salesChannelContext = $this->salesChannelContextFactory->create(
                Uuid::randomHex(),
                $salesChannelId,
                $options
            );
        } catch (NoContextDataException) {
            // Mail simulations can use generated sales-channel shells without persisted context data.
            // Rendering can continue with the existing template data in that case.
            return;
        }

        $event->addTemplateData(self::SALES_CHANNEL_CONTEXT, $salesChannelContext);
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
