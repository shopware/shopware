<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\Struct;

class BusinessEventDefinition extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $class;

    /**
     * @var bool
     */
    protected $mailAware;

    /**
     * @var bool
     */
    protected $logAware;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var bool
     */
    protected $salesChannelAware;

    /**
     * @internal (FEATURE_NEXT_8225)
     */
    protected ?bool $orderAware;

    /**
     * @internal (FEATURE_NEXT_8225)
     */
    protected ?bool $customerAware;

    /**
     * @internal (FEATURE_NEXT_8225)
     */
    protected ?bool $webhookAware;

    /**
     * @internal (FEATURE_NEXT_8225)
     */
    protected ?bool $userAware;

    public function __construct(
        string $name,
        string $class,
        bool $mailAware,
        bool $logAware,
        bool $salesChannelAware,
        array $data,
        ?bool $orderAware = null,
        ?bool $customerAware = null,
        ?bool $webhookAware = null,
        ?bool $userAware = null
    ) {
        $this->name = $name;
        $this->class = $class;
        $this->mailAware = $mailAware;
        $this->logAware = $logAware;
        $this->data = $data;
        $this->salesChannelAware = $salesChannelAware;
        $this->orderAware = $orderAware;
        $this->customerAware = $customerAware;
        $this->webhookAware = $webhookAware;
        $this->userAware = $userAware;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function isMailAware(): bool
    {
        return $this->mailAware;
    }

    public function setMailAware(bool $mailAware): void
    {
        $this->mailAware = $mailAware;
    }

    public function isLogAware(): bool
    {
        return $this->logAware;
    }

    public function setLogAware(bool $logAware): void
    {
        $this->logAware = $logAware;
    }

    public function getApiAlias(): string
    {
        return 'business_event_definition';
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function isSalesChannelAware(): bool
    {
        return $this->salesChannelAware;
    }

    public function setSalesChannelAware(bool $salesChannelAware): void
    {
        $this->salesChannelAware = $salesChannelAware;
    }

    public function getOrderAware(): ?bool
    {
        return $this->orderAware;
    }

    public function setOrderAware(?bool $orderAware): void
    {
        $this->orderAware = $orderAware;
    }

    public function getCustomerAware(): ?bool
    {
        return $this->customerAware;
    }

    public function setCustomerAware(?bool $customerAware): void
    {
        $this->customerAware = $customerAware;
    }

    public function getWebhookAware(): ?bool
    {
        return $this->webhookAware;
    }

    public function setWebhookAware(?bool $webhookAware): void
    {
        $this->webhookAware = $webhookAware;
    }

    /**
     * @feature-deprecated (FEATURE_NEXT_8225)
     */
    public function jsonSerialize(): array
    {
        $vars = parent::jsonSerialize();

        if (!Feature::isActive('FEATURE_NEXT_8225')) {
            unset($vars['orderAware'], $vars['customerAware'], $vars['userAware'], $vars['webhookAware']);
        }

        return $vars;
    }
}
