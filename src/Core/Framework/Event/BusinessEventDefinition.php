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
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use $aware instead.
     *
     * @var bool
     */
    protected $mailAware;

    /**
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use $aware instead.
     *
     * @var bool
     */
    protected $logAware;

    /**
     * @var array
     */
    protected $data;

    /**
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use $aware instead.
     *
     * @var bool
     */
    protected $salesChannelAware;

    protected array $aware = [];

    public function __construct(
        string $name,
        string $class,
        bool $mailAware,
        bool $logAware,
        bool $salesChannelAware,
        array $data,
        array $aware = []
    ) {
        $this->name = $name;
        $this->class = $class;
        $this->mailAware = $mailAware;
        $this->logAware = $logAware;
        $this->data = $data;
        $this->salesChannelAware = $salesChannelAware;
        $this->aware = $aware;
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

    /**
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::getAware() instead.
     */
    public function isMailAware(): bool
    {
        Feature::triggerDeprecated('FEATURE_NEXT_17858', 'v6.4.6', 'v6.5.0', 'Will be removed in v6.5.0, use BusinessEventDefinition::getAware() instead.');

        return $this->mailAware;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::addAware() instead.
     */
    public function setMailAware(bool $mailAware): void
    {
        Feature::triggerDeprecated('FEATURE_NEXT_17858', 'v6.4.6', 'v6.5.0', 'Will be removed in v6.5.0, use BusinessEventDefinition::addAware() instead.');

        $this->mailAware = $mailAware;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::getAware() instead.
     */
    public function isLogAware(): bool
    {
        Feature::triggerDeprecated('FEATURE_NEXT_17858', 'v6.4.6', 'v6.5.0', 'Will be removed in v6.5.0, use BusinessEventDefinition::getAware() instead.');

        return $this->logAware;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::addAware() instead.
     */
    public function setLogAware(bool $logAware): void
    {
        Feature::triggerDeprecated('FEATURE_NEXT_17858', 'v6.4.6', 'v6.5.0', 'Will be removed in v6.5.0, use BusinessEventDefinition::addAware() instead.');

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

    /**
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::getAware() instead.
     */
    public function isSalesChannelAware(): bool
    {
        Feature::triggerDeprecated('FEATURE_NEXT_17858', 'v6.4.6', 'v6.5.0', 'Will be removed in v6.5.0, use BusinessEventDefinition::getAware() instead.');

        return $this->salesChannelAware;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::addAware() instead.
     */
    public function setSalesChannelAware(bool $salesChannelAware): void
    {
        Feature::triggerDeprecated('FEATURE_NEXT_17858', 'v6.4.6', 'v6.5.0', 'Will be removed in v6.5.0, use BusinessEventDefinition::addAware() instead.');

        $this->salesChannelAware = $salesChannelAware;
    }

    public function addAware(string $key): void
    {
        $this->aware[] = $key;
    }

    public function getAware(string $key): bool
    {
        return \in_array($key, $this->aware, true);
    }
}
