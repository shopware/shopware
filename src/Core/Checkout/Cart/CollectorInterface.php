<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Struct\StructCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CollectorInterface
{
    /**
     * Triggered first to prepare the fetch definitions for the current cart
     */
    public function prepare(StructCollection $definitions, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void;

    /**
     * Triggers after all collectors::prepare functions called
     */
    public function collect(StructCollection $fetchDefinitions, StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void;

    /**
     * Triggers after all collectors::collect functions called.
     * Enrich all line items with missing data. Each collector has to care about their different line items
     */
    public function enrich(StructCollection $data, Cart $cart, SalesChannelContext $context, CartBehavior $behavior): void;
}
