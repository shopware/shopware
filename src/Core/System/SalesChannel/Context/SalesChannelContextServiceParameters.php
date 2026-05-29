<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class SalesChannelContextServiceParameters extends Struct
{
    /**
     * @deprecated tag:v6.8.0 - Parameter `imitatingUserId` will be removed
     *
     * @param ContextToken $token
     * @param ?CartToken $cartToken
     */
    public function __construct(
        protected string $salesChannelId,
        protected string $token,
        protected ?string $languageId = null,
        // used as fallback if no currency is set in the existing context
        protected ?string $currencyId = null,
        protected ?string $domainId = null,
        protected ?Context $originalContext = null,
        protected ?string $customerId = null,
        protected ?string $imitatingUserId = null,
        protected ?string $overwriteCurrencyId = null,
        protected ?string $cartToken = null,
    ) {
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    /**
     * @return ContextToken
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return ?CartToken
     */
    public function getCartToken(): ?string
    {
        return $this->cartToken;
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function getOverwriteCurrencyId(): ?string
    {
        return $this->overwriteCurrencyId;
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

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public function getImitatingUserId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        return $this->imitatingUserId;
    }
}
