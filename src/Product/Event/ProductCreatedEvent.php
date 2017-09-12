<?php

namespace Shopware\Product\Event;

use Symfony\Component\EventDispatcher\Event;

class ProductCreatedEvent extends Event
{
    const EVENT_NAME = 'product.created';

    /**
     * @var string[]
     */
    private $createdUuids;

    private $errors;

    public function __construct(array $createdUuids, array $errors)
    {
        $this->createdUuids = $createdUuids;
        $this->errors = $errors;
    }

    /**
     * @return string[]
     */
    public function getCreatedUuids(): array
    {
        return $this->createdUuids;
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