<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

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
     * @feature-deprecated (flag:FEATURE_NEXT_8225) - tag:v6.5.0 - Will be removed in v6.5.0, use $aware instead.
     *
     * @var bool
     */
    protected $mailAware;

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_8225) - tag:v6.5.0 - Will be removed in v6.5.0, use $aware instead.
     *
     * @var bool
     */
    protected $logAware;

    /**
     * @var array
     */
    protected $data;

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_8225) - tag:v6.5.0 - Will be removed in v6.5.0, use $aware instead.
     *
     * @var bool
     */
    protected $salesChannelAware;

    /**
     * @internal (flag:FEATURE_NEXT_8225)
     */
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
     * @feature-deprecated (flag:FEATURE_NEXT_8225) - tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::getAware() instead.
     */
    public function isMailAware(): bool
    {
        return $this->mailAware;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_8225) - tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::addAware() instead.
     */
    public function setMailAware(bool $mailAware): void
    {
        $this->mailAware = $mailAware;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_8225) - tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::getAware() instead.
     */
    public function isLogAware(): bool
    {
        return $this->logAware;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_8225) - tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::addAware() instead.
     */
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

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_8225) - tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::getAware() instead.
     */
    public function isSalesChannelAware(): bool
    {
        return $this->salesChannelAware;
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_8225) - tag:v6.5.0 - Will be removed in v6.5.0, use BusinessEventDefinition::addAware() instead.
     */
    public function setSalesChannelAware(bool $salesChannelAware): void
    {
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
