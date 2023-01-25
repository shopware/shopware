<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class SalesChannelContextServiceParameters extends Struct
{
    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string|null
     */
    protected $languageId;

    /**
     * @var string|null
     */
    protected $currencyId;

    /**
     * @var string|null
     */
    protected $domainId;

    /**
     * @var Context|null
     */
    protected $originalContext;

    public function __construct(
        string $salesChannelId,
        string $token,
        ?string $languageId = null,
        ?string $currencyId = null,
        ?string $domainId = null,
        ?Context $originalContext = null,
        protected ?string $customerId = null
    ) {
        $this->salesChannelId = $salesChannelId;
        $this->token = $token;
        $this->languageId = $languageId;
        $this->currencyId = $currencyId;
        $this->domainId = $domainId;
        $this->originalContext = $originalContext;
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
}
