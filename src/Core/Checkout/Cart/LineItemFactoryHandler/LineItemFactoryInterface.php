<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItemFactoryHandler;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface LineItemFactoryInterface
{
    public function supports(string $type): bool;

    public function create(array $data, SalesChannelContext $context): LineItem;

    public function update(LineItem $lineItem, array $data, SalesChannelContext $context): void;
}
