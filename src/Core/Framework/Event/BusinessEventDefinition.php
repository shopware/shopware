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

    public function __construct(
        string $name,
        string $class,
        bool $mailAware,
        bool $logAware,
        bool $salesChannelAware,
        array $data
    ) {
        $this->name = $name;
        $this->class = $class;
        $this->mailAware = $mailAware;
        $this->logAware = $logAware;
        $this->data = $data;
        $this->salesChannelAware = $salesChannelAware;
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
}
