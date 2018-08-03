<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationCollection;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\Plugin\PluginStruct;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class PaymentMethodStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $pluginId;

    /**
     * @var string
     */
    protected $technicalName;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $additionalDescription;

    /**
     * @var string|null
     */
    protected $template;

    /**
     * @var string|null
     */
    protected $class;

    /**
     * @var string|null
     */
    protected $table;

    /**
     * @var bool
     */
    protected $hide;

    /**
     * @var float|null
     */
    protected $percentageSurcharge;

    /**
     * @var float|null
     */
    protected $absoluteSurcharge;

    /**
     * @var string|null
     */
    protected $surchargeString;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var bool
     */
    protected $allowEsd;

    /**
     * @var string|null
     */
    protected $usedIframe;

    /**
     * @var bool
     */
    protected $hideProspect;

    /**
     * @var string|null
     */
    protected $action;

    /**
     * @var int|null
     */
    protected $source;

    /**
     * @var bool
     */
    protected $mobileInactive;

    /**
     * @var string|null
     */
    protected $riskRules;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var PluginStruct|null
     */
    protected $plugin;

    /**
     * @var PaymentMethodTranslationCollection|null
     */
    protected $translations;

    /**
     * @var OrderTransactionCollection|null
     */
    protected $transactions;

    /**
     * @var OrderCollection|null
     */
    protected $orders;

    /**
     * @var CustomerCollection|null
     */
    protected $customers;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    public function getPluginId(): ?string
    {
        return $this->pluginId;
    }

    public function setPluginId(?string $pluginId): void
    {
        $this->pluginId = $pluginId;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAdditionalDescription(): string
    {
        return $this->additionalDescription;
    }

    public function setAdditionalDescription(string $additionalDescription): void
    {
        $this->additionalDescription = $additionalDescription;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): void
    {
        $this->template = $template;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(?string $class): void
    {
        $this->class = $class;
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    public function setTable(?string $table): void
    {
        $this->table = $table;
    }

    public function getHide(): bool
    {
        return $this->hide;
    }

    public function setHide(bool $hide): void
    {
        $this->hide = $hide;
    }

    public function getPercentageSurcharge(): ?float
    {
        return $this->percentageSurcharge;
    }

    public function setPercentageSurcharge(?float $percentageSurcharge): void
    {
        $this->percentageSurcharge = $percentageSurcharge;
    }

    public function getAbsoluteSurcharge(): ?float
    {
        return $this->absoluteSurcharge;
    }

    public function setAbsoluteSurcharge(?float $absoluteSurcharge): void
    {
        $this->absoluteSurcharge = $absoluteSurcharge;
    }

    public function getSurchargeString(): ?string
    {
        return $this->surchargeString;
    }

    public function setSurchargeString(?string $surchargeString): void
    {
        $this->surchargeString = $surchargeString;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getAllowEsd(): bool
    {
        return $this->allowEsd;
    }

    public function setAllowEsd(bool $allowEsd): void
    {
        $this->allowEsd = $allowEsd;
    }

    public function getUsedIframe(): ?string
    {
        return $this->usedIframe;
    }

    public function setUsedIframe(?string $usedIframe): void
    {
        $this->usedIframe = $usedIframe;
    }

    public function getHideProspect(): bool
    {
        return $this->hideProspect;
    }

    public function setHideProspect(bool $hideProspect): void
    {
        $this->hideProspect = $hideProspect;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getSource(): ?int
    {
        return $this->source;
    }

    public function setSource(?int $source): void
    {
        $this->source = $source;
    }

    public function getMobileInactive(): bool
    {
        return $this->mobileInactive;
    }

    public function setMobileInactive(bool $mobileInactive): void
    {
        $this->mobileInactive = $mobileInactive;
    }

    public function getRiskRules(): ?string
    {
        return $this->riskRules;
    }

    public function setRiskRules(?string $riskRules): void
    {
        $this->riskRules = $riskRules;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getPlugin(): ?PluginStruct
    {
        return $this->plugin;
    }

    public function setPlugin(PluginStruct $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function getTranslations(): ?PaymentMethodTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(PaymentMethodTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getTransactions(): ?OrderTransactionCollection
    {
        return $this->transactions;
    }

    public function setTransactions(OrderTransactionCollection $transactions): void
    {
        $this->transactions = $transactions;
    }

    public function getOrders(): ?OrderCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }
}
