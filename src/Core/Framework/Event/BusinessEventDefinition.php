<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('business-ops')]
class BusinessEventDefinition extends Struct
{
    /**
     * @param array<string, mixed> $data
     * @param list<string> $aware
     */
    public function __construct(
        protected string $name,
        protected string $class,
        protected array $data,
        protected array $aware = []
    ) {
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

    public function getApiAlias(): string
    {
        return 'business_event_definition';
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
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
