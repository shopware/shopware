<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @final
 */
#[Package('after-sales')]
class MailTemplateRenderContextEvent implements ShopwareEvent
{
    /**
     * @param array<string, mixed> $templateData
     */
    public function __construct(
        private array $templateData,
        private readonly Context $context,
        private readonly ?SalesChannelEntity $salesChannel = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    public function setTemplateData(array $templateData): void
    {
        $this->templateData = $templateData;
    }

    public function addTemplateData(string $key, mixed $value): void
    {
        $this->templateData[$key] = $value;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }
}
