<?php declare(strict_types=1);

namespace Shopware\Context\Service;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Api\Context\Collection\ContextRuleBasicCollection;
use Shopware\Api\Context\Repository\ContextRuleRepository;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Cart\Cart\CartCollector;
use Shopware\Cart\Cart\CartPersisterInterface;
use Shopware\Cart\Cart\CartProcessor;
use Shopware\Cart\Cart\CartValidator;
use Shopware\Cart\Cart\CircularCartCalculation;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Exception\CartTokenNotFoundException;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\Price\Struct\CartPrice;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\CartBridge\Service\StoreFrontCartService;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\StorefrontContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ContextRuleLoader
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
     * @var ContextRuleRepository
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
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        CartPersisterInterface $cartPersister,
        TaxDetector $taxDetector,
        CartCollector $cartCollector,
        CartProcessor $cartProcessor,
        CartValidator $cartValidator,
        CacheItemPoolInterface $cache,
        ContextRuleRepository $repository,
        SerializerInterface $serializer,
        ContainerInterface $container
    ) {
        $this->cartPersister = $cartPersister;
        $this->taxDetector = $taxDetector;
        $this->cartCollector = $cartCollector;
        $this->cartValidator = $cartValidator;
        $this->cartProcessor = $cartProcessor;
        $this->cache = $cache;
        $this->repository = $repository;
        $this->serializer = $serializer;
        $this->container = $container;
    }

    public function loadMatchingRules(StorefrontContext $context, ?string $cartToken)
    {
        $context = clone $context;

        try {
            $calculated = $this->cartPersister->loadCalculated(
                (string) $cartToken,
                StoreFrontCartService::CART_NAME
            );
        } catch (CartTokenNotFoundException $e) {
            $calculated = new CalculatedCart(
                Cart::createNew(StoreFrontCartService::CART_NAME, $cartToken),
                new CalculatedLineItemCollection(),
                CartPrice::createEmpty($this->taxDetector->getTaxState($context)),
                new DeliveryCollection()
            );
        }

        $rules = $this->loadRules($context->getShopContext());

        $valid = false;

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
            $context->setContextRulesIds($rules->getIds());

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

        $this->container->get(StoreFrontCartService::class)->setCalculated($calculated, $context);

        return $rules;
    }

    private function loadRules(ShopContext $context): ContextRuleBasicCollection
    {
        if ($this->rules !== null) {
            return $this->rules;
        }
        $key = 'context_rules_key';

        $cacheItem = $this->cache->getItem($key);

        if ($rules = $cacheItem->get()) {
            $rules = $this->serializer->deserialize($rules, '', 'json');
            $this->rules = $rules;

            return $this->rules;
        }

        $rules = $this->repository->search(new Criteria(), $context);
        $this->rules = $rules;

        $cacheItem->set(
            $this->serializer->serialize($rules, 'json')
        );
        $this->cache->save($cacheItem);

        return $rules;
    }

    private function cartChanged(CalculatedCart $previous, CalculatedCart $current): bool
    {
        return md5(json_encode($previous)) !== md5(json_encode($current));
    }
}
