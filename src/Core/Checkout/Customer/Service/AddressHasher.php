<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Service;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\AddressHashStruct;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Event\AddressHashEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class AddressHasher
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function isMatching(string $hash, CustomerAddressEntity|OrderAddressEntity $address): bool
    {
        return $hash === $this->generate($address);
    }

    public function generate(CustomerAddressEntity|OrderAddressEntity $address): string
    {
        $event = new AddressHashEvent(AddressHashStruct::createFromAddress($address));
        $this->eventDispatcher->dispatch($event);

        return Hasher::hash($event->hashStruct, 'sha256');
    }
}
