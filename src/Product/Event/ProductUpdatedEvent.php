<?php

namespace Shopware\Product\Event;

use Symfony\Component\EventDispatcher\Event;

class ProductUpdatedEvent extends Event
{
    const EVENT_NAME = 'product.updated';

    /**
     * @var string[]
     */
    private $updatedUuids;

    private $errors;

    public function __construct(array $updatedUuids, array $errors = [])
    {
        $this->updatedUuids = $updatedUuids;
        $this->errors = $errors;
    }

    /**
     * @return string[]
     */
    public function getUpdatedUuids(): array
    {
        return $this->updatedUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}