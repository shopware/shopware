<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;

class CheckoutRuleLoader
{
    private const MAX_ITERATION = 5;
    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var RuleCollection
     */
    private $rules;

    /**
     * @var CartService
     */
    private $storeFrontCartService;

    /**
     * @var Processor
     */
    private $processor;

    public function __construct(
        CartPersisterInterface $cartPersister,
        Processor $processor,
        CacheItemPoolInterface $cache,
        RepositoryInterface $repository,
        CartService $storeFrontCartService
    ) {
        $this->cartPersister = $cartPersister;
        $this->cache = $cache;
        $this->repository = $repository;
        $this->storeFrontCartService = $storeFrontCartService;
        $this->processor = $processor;
    }

    public function loadMatchingRules(CheckoutContext $context, ?string $cartToken)
    {
        try {
            $cart = $this->cartPersister->load(
                (string) $cartToken,
                CartService::CART_NAME,
                $context
            );
        } catch (CartTokenNotFoundException $e) {
            $cart = new Cart(CartService::CART_NAME, $cartToken);
        }

        $rules = $this->loadRules($context->getContext());

        $rules->sortByPriority();

        $valid = false;

        $context->setRuleIds($rules->getIds());

        $iteration = 1;

        while (!$valid) {
            if ($iteration > self::MAX_ITERATION) {
                break;
            }

            //find rules which matching current cart and context state
            $rules = $rules->filterMatchingRules($cart, $context);

            //place rules into context for further usages
            $context->setRuleIds($rules->getIds());

            //recalculate cart for new context rules
            $new = $this->processor->process($cart, $context);

            if ($this->cartChanged($cart, $new)) {
                $valid = false;
            }

            $cart = $new;

            ++$iteration;
        }

        $this->storeFrontCartService->setCart($cart);

        return $rules;
    }

    private function loadRules(Context $context): RuleCollection
    {
        if ($this->rules !== null) {
            return $this->rules;
        }
        $key = 'rules_key_' . $context->getTenantId();

        $cacheItem = $this->cache->getItem($key);

        try {
            $rules = $rules = $cacheItem->isHit();
            if ($rules) {
                $this->rules = unserialize($rules);

                return $this->rules;
            }
        } catch (\Throwable $e) {
        }

        $rules = $this->repository->search(new Criteria(), $context);

        /** @var RuleCollection $rules */
        $rules = $rules->getEntities();
        $this->rules = $rules;

        $cacheItem->set(serialize($this->rules));
        $this->cache->save($cacheItem);

        return $this->rules;
    }

    private function cartChanged(Cart $previous, Cart $current): bool
    {
        return
            $previous->getLineItems()->count() !== $current->getLineItems()->count()
            ||
            $previous->getPrice()->getTotalPrice() !== $current->getPrice()->getTotalPrice()
            ||
            $previous->getLineItems()->getKeys() !== $current->getLineItems()->getKeys()
            ||
            $previous->getLineItems()->getTypes() !== $current->getLineItems()->getTypes()
        ;
    }
}
