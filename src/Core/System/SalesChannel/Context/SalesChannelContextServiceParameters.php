<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class SalesChannelContextServiceParameters extends Struct
{
    public function __construct(
        protected string $salesChannelId,
        protected string $token,
        protected ?string $languageId = null,
        protected ?string $currencyId = null,
        protected ?string $domainId = null,
        protected ?Context $originalContext = null,
        protected ?string $customerId = null,
        protected ?string $imitatingUserId = null
    ) {
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function getDomainId(): ?string
    {
        return $this->domainId;
    }

    public function getOriginalContext(): ?Context
    {
        return $this->originalContext;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function getImitatingUserId(): ?string
    {
        return $this->imitatingUserId;
    }
}
