<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('core')]
class ChangeSet extends Struct
{
    /**
     * @var array
     */
    protected $state = [];

    /**
     * @var array
     */
    protected $after = [];

    /**
     * @var bool
     */
    protected $isDelete;

    public function __construct(
        array $state,
        array $payload,
        bool $isDelete
    ) {
        $this->state = $state;

        // calculate changes
        $changes = array_intersect_key($payload, $state);

        // validate data types
        foreach ($changes as $property => $after) {
            $before = $state[$property];
            $string = (string) $after;
            if ($string === $before) {
                continue;
            }
            $this->after[$property] = $after;
        }
        $this->isDelete = $isDelete;
    }

    /**
     * @return array|mixed|string|null
     */
    public function getBefore(?string $property)
    {
        if ($property) {
            return $this->state[$property] ?? null;
        }

        return $this->state;
    }

    /**
     * @return array|mixed|string|null
     */
    public function getAfter(?string $property)
    {
        if ($property) {
            return $this->after[$property] ?? null;
        }

        return $this->after;
    }

    public function hasChanged(string $property): bool
    {
        return \array_key_exists($property, $this->after) || $this->isDelete;
    }

    public function getApiAlias(): string
    {
        return 'dal_change_set';
    }
}
