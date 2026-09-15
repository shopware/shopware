<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Event\CartContextHashEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class CartContextHasher
{
    /**
     * Address fields that decide how an order is taxed and where it is delivered to.
     *
     * @var list<string>
     */
    private const ADDRESS_HASH_FIELDS = [
        'id',
        'countryId',
        'countryStateId',
        'zipcode',
        'city',
    ];

    /**
     * Customer fields that decide whether the order is taxed as a B2B intra-community delivery.
     *
     * @var list<string>
     */
    private const CUSTOMER_HASH_FIELDS = [
        'accountType',
        'company',
        'vatIds',
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function isMatching(string $hash, Cart $cart, SalesChannelContext $context): bool
    {
        return $hash === $this->generate($cart, $context);
    }

    /**
     * @throws \JsonException
     */
    public function generate(Cart $cart, SalesChannelContext $context): string
    {
        $struct = new CartContextHashStruct();

        $struct->setPrice($cart->getPrice()->getRawTotal());
        $struct->setShippingMethod($context->getShippingMethod()->getId());
        $struct->setPaymentMethod($context->getPaymentMethod()->getId());

        $customer = $context->getCustomer();

        $struct->setBillingAddress(self::getHashContent($customer?->getActiveBillingAddress(), self::ADDRESS_HASH_FIELDS));
        $struct->setShippingAddress(self::getHashContent(
            $context->getShippingLocation()->getAddress() ?? $customer?->getActiveShippingAddress(),
            self::ADDRESS_HASH_FIELDS
        ));
        $struct->setCustomer(self::getHashContent($customer, self::CUSTOMER_HASH_FIELDS));

        foreach ($cart->getLineItems()->getElements() as $item) {
            $struct->addLineItem($item->getId(), $item->getHashContent());
        }

        $event = $this
            ->eventDispatcher
            ->dispatch(new CartContextHashEvent($context, $cart, $struct));

        return Hasher::hash($event->getHashStruct(), 'sha256');
    }

    /**
     * Reads the given fields from the entity. Uses `getVars()` instead of the getters to
     * avoid throwing when a non-nullable typed property is accessed.
     *
     * @param list<string> $fields
     *
     * @return array<string, mixed>|null
     */
    private static function getHashContent(?Entity $entity, array $fields): ?array
    {
        if ($entity === null) {
            return null;
        }

        $vars = $entity->getVars();

        $content = [];
        foreach ($fields as $field) {
            $content[$field] = $vars[$field] ?? null;
        }

        return $content;
    }
}
