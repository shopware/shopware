<?php

namespace Shopware\Product\Event;

use Symfony\Component\EventDispatcher\Event;

class ProductWrittenEvent extends Event
{
    const EVENT_NAME = 'product.written';

    /**
     * @var string[]
     */
    private $productUuids;

    private $errors;

    public function __construct(array $productUuids, array $errors = [])
    {
        $this->productUuids = $productUuids;
        $this->errors = $errors;
    }

    /**
     * @return string[]
     */
    public function getProductUuids(): array
    {
        return $this->productUuids;
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