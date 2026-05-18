<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeValidateEvent::class => 'addSalesChannelContext',
        ];
    }

    public function addSalesChannelContext(MailBeforeValidateEvent $event): void
    {
        $data = $event->getData();
        $templateData = $event->getTemplateData();

        $salesChannelId = $this->getSalesChannelId($data, $templateData);
        if ($salesChannelId === null) {
            return;
        }

        $themeId = $this->getThemeId($salesChannelId);
        if ($themeId !== null && !isset($templateData['themeId'])) {
            $templateData['themeId'] = $themeId;
        }

        if (($templateData[self::SALES_CHANNEL_CONTEXT] ?? null) instanceof SalesChannelContext) {
            $event->setTemplateData($templateData);

            return;
        }

        $context = $event->getContext();
        $languageId = $context->getLanguageId();
        $currencyId = $context->getCurrencyId();

        $options = [
            SalesChannelContextService::LANGUAGE_ID => $languageId,
            SalesChannelContextService::CURRENCY_ID => $currencyId,
        ];

        $templateData[self::SALES_CHANNEL_CONTEXT] = $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannelId,
            $options
        );

        $event->setTemplateData($templateData);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $templateData
     */
    private function getSalesChannelId(array $data, array $templateData): ?string
    {
        $salesChannelContext = $templateData[self::SALES_CHANNEL_CONTEXT] ?? null;
        if ($salesChannelContext instanceof SalesChannelContext) {
            return $salesChannelContext->getSalesChannelId();
        }

        $salesChannelId = $data['salesChannelId'] ?? $templateData['salesChannelId'] ?? null;
        if (!\is_string($salesChannelId) || !Uuid::isValid($salesChannelId)) {
            return null;
        }

        return $salesChannelId;
    }

    private function getThemeId(string $salesChannelId): ?string
    {
        $themeId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`theme_id`)) FROM `theme_sales_channel` WHERE `sales_channel_id` = :salesChannelId',
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]
        );

        if (!\is_string($themeId) || !Uuid::isValid($themeId)) {
            return null;
        }

        return $themeId;
    }
}
