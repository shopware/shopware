<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Util;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Framework\Context;
use Shopware\Checkout\CustomerContext;
use Shopware\Checkout\Cart\Cart\CartCollector;
use Shopware\Checkout\Cart\Cart\CartPersisterInterface;
use Shopware\Checkout\Cart\Cart\CartProcessor;
use Shopware\Checkout\Cart\Cart\CartValidator;
use Shopware\Checkout\Cart\Cart\CircularCartCalculation;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Checkout\Cart\StoreFrontCartService;
use Shopware\Checkout\Cart\Tax\TaxDetector;
use Shopware\Checkout\Rule\Collection\ContextRuleBasicCollection;
use Shopware\Checkout\Rule\ContextRuleRepository;
use Shopware\Framework\ORM\Search\Criteria;
use Symfony\Component\Serializer\SerializerInterface;

class CustomerContextRuleLoader
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
     * @var \Shopware\Checkout\Rule\ContextRuleRepository
     */
    private $repository;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var ContextRuleBasicCollection
     */
    private $rules;

    /**
     * @var StoreFrontCartService
     */
    private $storeFrontCartService;

    public function __construct(
        CartPersisterInterface $cartPersister,
        TaxDetector $taxDetector,
        CartCollector $cartCollector,
        CartProcessor $cartProcessor,
        CartValidator $cartValidator,
        CacheItemPoolInterface $cache,
        ContextRuleRepository $repository,
        SerializerInterface $serializer,
        StoreFrontCartService $storeFrontCartService
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

    public function loadMatchingRules(CustomerContext $context, ?string $cartToken)
    {
        $context = clone $context;

        try {
            $calculated = $this->cartPersister->loadCalculated(
                (string) $cartToken,
                StoreFrontCartService::CART_NAME,
                $context
            );
        } catch (CartTokenNotFoundException $e) {
            $calculated = new CalculatedCart(
                Cart::createNew(StoreFrontCartService::CART_NAME, $cartToken),
                new CalculatedLineItemCollection(),
                CartPrice::createEmpty($this->taxDetector->getTaxState($context)),
                new DeliveryCollection()
            );
        }

        $rules = $this->loadRules($context->getContext());

        $rules->sortByPriority();

        $valid = false;

        $context->setContextRuleIds($rules->getIds());

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
            $context->setContextRuleIds($rules->getIds());

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

    private function loadRules(Context $context): ContextRuleBasicCollection
    {
        if ($this->rules !== null) {
            return $this->rules;
        }
        $key = 'context_rules_key_' . $context->getTenantId();

        $cacheItem = $this->cache->getItem($key);

        try {
            if ($rules = $cacheItem->isHit()) {
                $this->rules = unserialize($rules);

                return $this->rules;
            }
        } catch (\Throwable $e) {
        }

        $rules = $this->repository->search(new Criteria(), $context);
        $this->rules = $rules;

        $cacheItem->set(serialize($rules));
        $this->cache->save($cacheItem);

        return $rules;
    }

    private function cartChanged(CalculatedCart $previous, CalculatedCart $current): bool
    {
        return md5(json_encode($previous)) !== md5(json_encode($current));
    }
}
