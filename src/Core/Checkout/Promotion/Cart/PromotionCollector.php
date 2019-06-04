<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\PromotionGatewayInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionCollector implements CartDataCollectorInterface
{
    private $gateway;

    public function __construct(PromotionGatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * This function is used to collect our promotion data for our cart.
     * It queries the database for all promotions with codes within our cart extension
     * along with all non-code promotions that are applied automatically if conditions are met.
     * The eligible promotions will then be passed on to the enrichment function.
     */
    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        /** @var array $autoPromotions */
        $autoPromotions = $this->searchPromotionsAuto($data, $context);

        /** @var array $allCodes */
        $allCodes = $original
            ->getLineItems()
            ->filterType(PromotionProcessor::LINE_ITEM_TYPE)
            ->getReferenceIds();

        /** @var array $codePromotions */
        $codePromotions = $this->searchPromotionsByCodes($data, $allCodes, $context);

        /** @var array $allPromotions */
        $allPromotions = array_merge($autoPromotions, $codePromotions);

        // check if max allowed redemption of promotion have been reached or not
        // if max redemption has been reached promotion will not be added
        /** @var PromotionEntity[] $eligiblePromotions */
        $eligiblePromotions = $this->getEligiblePromotionsWithDiscounts($allPromotions, $context->getCustomer());

        // if we do have promotions, set them to be processed
        // otherwise make sure to remove the entry to avoid any processing
        // within our promotions scope
        if (count($eligiblePromotions) >= 0) {
            $data->set(PromotionProcessor::DATA_KEY, new PromotionCollection($eligiblePromotions));
        } else {
            $data->remove(PromotionProcessor::DATA_KEY);
        }
    }

    /**
     * Gets either the cached list of auto-promotions that
     * are valid, or loads them from the database.
     */
    private function searchPromotionsAuto(CartDataCollection $data, SalesChannelContext $context): array
    {
        if ($data->has('promotions-auto')) {
            return $data->get('promotions-auto');
        }

        /** @var PromotionCollection $automaticPromotions */
        $automaticPromotions = $this->gateway->getAutomaticPromotions($context);

        $data->set('promotions-auto', $automaticPromotions->getElements());

        return $automaticPromotions->getElements();
    }

    /**
     * Gets all promotions by using the provided list of codes.
     * The promotions will be either taken from a cached list of a previous call,
     * or are loaded directly from the database if a certain code is new
     * and has not yet been fetched.
     */
    private function searchPromotionsByCodes(CartDataCollection $data, array $allCodes, SalesChannelContext $context): array
    {
        $keyCacheList = 'promotions-code';

        // create a new cached list that is empty at first
        if (!$data->has($keyCacheList)) {
            $data->set($keyCacheList, []);
        }

        /** @var array $previousCachedPromotions */
        $previousCachedPromotions = $data->get($keyCacheList);
        /** @var array $newCachedPromotions */
        $newCachedPromotions = [];

        // our data is a runtime cached structure
        // but when removing a line item, the collect
        // function gets called multiple times.
        // in the first iterations we still have a promotion code item
        // and then it is suddenly gone. so we also have to remove
        // entities from our cache if the code is suddenly not provided anymore.
        /*
         * @var PromotionEntity
         */
        foreach ($previousCachedPromotions as $code => $promotion) {
            // only keep item, if code is still provided and required
            if (in_array($code, $allCodes, true)) {
                $newCachedPromotions[$code] = $promotion;
            }
        }

        $codesToFetch = [];

        // let's find out what promotions we
        // really need to fetch from our database.
        /* @var string $code */
        foreach ($allCodes as $code) {
            // check if promotion is already cached
            if (array_key_exists($code, $previousCachedPromotions)) {
                continue;
            }

            // fetch that new code
            $codesToFetch[] = $code;

            // add a new entry with null
            // so if we cant fetch it, we do at least
            // tell our cache that we have tried it
            $newCachedPromotions[$code] = null;
        }

        // if we have new codes to fetch
        // make sure to load it and assign it to
        // the code in our cache list.
        if (count($codesToFetch) > 0) {
            /* @var PromotionCollection $newPromotions */
            $newPromotions = $this->gateway->getByCodes($codesToFetch, $context);

            /** @var PromotionEntity $promotion */
            foreach ($newPromotions->getElements() as $promotion) {
                $newCachedPromotions[$promotion->getCode()] = $promotion;
            }
        }

        // update our cached list with
        // the latest cleaned array
        $data->set($keyCacheList, $newCachedPromotions);

        // we return a flat list
        // so clear null entries (if promotion was not found)
        /** @var array $values */
        $values = array_values($data->get($keyCacheList));

        return array_filter($values);
    }

    /**
     * function returns all promotions that have discounts and that are eligible
     * (function validates that max usage or customer max usage hasn't exceeded)
     */
    private function getEligiblePromotionsWithDiscounts(array $promotions, ?CustomerEntity $customer): array
    {
        $eligiblePromotions = [];

        // array that holds all excluded promotion ids.
        // if a promotion has exclusions they are added on the stack
        $exclusions = [];

        /** @var PromotionEntity $promotion */
        foreach ($promotions as $promotion) {
            // if promotion is on exclusions stack it is ignored
            if (isset($exclusions[$promotion->getId()])) {
                continue;
            }

            if (!$promotion->isOrderCountValid()) {
                continue;
            }

            if ($customer !== null && !$promotion->isOrderCountPerCustomerCountValid($customer->getId())) {
                continue;
            }

            // check if no discounts have been set
            if (!$promotion->hasDiscount()) {
                continue;
            }

            // add all exclusions to the stack
            foreach ($promotion->getExclusionIds() as $id) {
                $exclusions[$id] = true;
            }

            $eligiblePromotions[] = $promotion;
        }

        return $eligiblePromotions;
    }
}
