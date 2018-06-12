<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Cart\Cart\CartCollector;
use Shopware\Core\Checkout\Cart\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Cart\CartProcessor;
use Shopware\Core\Checkout\Cart\Cart\CartValidator;
use Shopware\Core\Checkout\Cart\Cart\CircularCartCalculation;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Storefront\CartService;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Symfony\Component\Serializer\SerializerInterface;

class CheckoutRuleLoader
{
    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    /**
     * @var CartCollector
     */
    private $cartCollector;

    /**
     * @var CartValidator
     */
    private $cartValidator;

    /**
     * @var CartProcessor
     */
    private $cartProcessor;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var \Shopware\Core\Content\Rule\RuleCollection
     */
    private $rules;

    /**
     * @var \Shopware\Core\Checkout\Cart\Storefront\CartService
     */
    private $storeFrontCartService;

    public function __construct(
        CartPersisterInterface $cartPersister,
        TaxDetector $taxDetector,
        CartCollector $cartCollector,
        CartProcessor $cartProcessor,
        CartValidator $cartValidator,
        CacheItemPoolInterface $cache,
        RepositoryInterface $repository,
        SerializerInterface $serializer,
        CartService $storeFrontCartService
    ) {
        $this->cartPersister = $cartPersister;
        $this->taxDetector = $taxDetector;
        $this->cartCollector = $cartCollector;
        $this->cartValidator = $cartValidator;
        $this->cartProcessor = $cartProcessor;
        $this->cache = $cache;
        $this->repository = $repository;
        $this->serializer = $serializer;
        $this->storeFrontCartService = $storeFrontCartService;
    }

    public function loadMatchingRules(CheckoutContext $context, ?string $cartToken)
    {
        $context = clone $context;

        try {
            $calculated = $this->cartPersister->loadCalculated(
                (string) $cartToken,
                CartService::CART_NAME,
                $context
            );
        } catch (CartTokenNotFoundException $e) {
            $calculated = new CalculatedCart(
                Cart::createNew(CartService::CART_NAME, $cartToken),
                new CalculatedLineItemCollection(),
                CartPrice::createEmpty($this->taxDetector->getTaxState($context)),
                new DeliveryCollection()
            );
        }

        $rules = $this->loadRules($context->getContext());

        $rules->sortByPriority();

        $valid = false;

        $context->setRuleIds($rules->getIds());

        //first collect additional data for cart processors outside the loop to prevent duplicate database access
        $processorData = $this->cartCollector->collect($calculated->getCart(), $context);

        $iteration = 1;

        while (!$valid) {
            if ($iteration > CircularCartCalculation::MAX_ITERATION) {
                break;
            }

            //find rules which matching current cart and context state
            $rules = $rules->filterMatchingRules($calculated, $context);

            //place rules into context for further usages
            $context->setRuleIds($rules->getIds());

            //recalculate cart for new context rules
            $newCart = $this->cartProcessor->process($calculated->getCart(), $context, $processorData);

            //if cart isn't valid, return the context rule finding
            $valid = $this->cartValidator->isValid($calculated, $context);

            if ($this->cartChanged($calculated, $newCart)) {
                $valid = false;
                $calculated = $newCart;
            }

            ++$iteration;
        }

        $this->storeFrontCartService->setCalculated($calculated);

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
            if ($rules = $cacheItem->isHit()) {
                $this->rules = unserialize($rules);

                return $this->rules;
            }
        } catch (\Throwable $e) {
        }

        $rules = $this->repository->search(new Criteria(), $context);
        $this->rules = $rules->getEntities();

        $cacheItem->set(serialize($this->rules));
        $this->cache->save($cacheItem);

        return $this->rules;
    }

    private function cartChanged(CalculatedCart $previous, CalculatedCart $current): bool
    {
        return md5(json_encode($previous)) !== md5(json_encode($current));
    }
}
