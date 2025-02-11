<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\AddressHashStruct;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class AddressHashEvent extends Event
{
    public function __construct(
        public readonly AddressHashStruct $hashStruct,
    ) {
    }
}
