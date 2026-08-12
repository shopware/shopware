<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cookie;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\App\AppHandlerIdentifier;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppCookieCollectListener
{
    private const ANY_PAYMENT_METHOD = '*';

    /**
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private readonly AppFeatureStorage $storage,
        private readonly EntityRepository $paymentMethodRepository,
    ) {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $features = $this->storage->forActiveApps(CookieConfig::class);

        $activeHandlerIdentifiers = $this->fetchActiveHandlerIdentifiers($features, $event);

        foreach ($features as $feature) {
            $config = $feature->config;

            if (!$this->isCookieAllowed($feature->appName, $config->activePaymentMethods, $activeHandlerIdentifiers)) {
                continue;
            }

            $entries = array_values(array_filter(
                $config->entries,
                fn (array $entry): bool => $this->isCookieAllowed($feature->appName, self::entryPaymentMethods($entry), $activeHandlerIdentifiers),
            ));

            $this->addCookieGroup($event->cookieGroupCollection, $config, $entries);
        }
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function addCookieGroup(CookieGroupCollection $cookieGroupCollection, CookieConfig $config, array $entries): void
    {
        $cookieGroup = $cookieGroupCollection->get($config->snippetName);
        if ($cookieGroup === null) {
            $cookieGroup = new CookieGroup($config->snippetName);
            $cookieGroupCollection->add($cookieGroup);
        }

        if ($config->snippetDescription !== null) {
            $cookieGroup->description = $config->snippetDescription;
        }

        if ($config->cookie !== null) {
            $cookieGroup->setCookie($config->cookie);
        }

        if ($config->value !== null) {
            $cookieGroup->value = $config->value;
        }

        if ($config->expiration !== null) {
            $cookieGroup->expiration = $config->expiration;
        }

        if ($config->entries === []) {
            return;
        }

        $cookieEntries = $cookieGroup->getEntries();
        if ($cookieEntries === null) {
            $cookieEntries = new CookieEntryCollection();
            $cookieGroup->setEntries($cookieEntries);
        }

        foreach ($entries as $entry) {
            $cookieEntry = new CookieEntry((string) $entry['cookie']);

            if (\array_key_exists('snippet_name', $entry)) {
                $cookieEntry->name = (string) $entry['snippet_name'];
            }

            if (\array_key_exists('snippet_description', $entry)) {
                $cookieEntry->description = (string) $entry['snippet_description'];
            }

            if (\array_key_exists('value', $entry)) {
                $cookieEntry->value = (string) $entry['value'];
            }

            if (\array_key_exists('expiration', $entry)) {
                $cookieEntry->expiration = (int) $entry['expiration'];
            }

            $cookieEntries->add($cookieEntry);
        }
    }

    /**
     * @param list<AppFeature<CookieConfig>> $features
     *
     * @return array<string, true> handler identifiers of the app payment methods active in the sales channel
     */
    private function fetchActiveHandlerIdentifiers(array $features, CookieGroupCollectEvent $event): array
    {
        if (!$this->hasPaymentMethodConditions($features)) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new PrefixFilter('handlerIdentifier', AppHandlerIdentifier::prefix()),
            new EqualsFilter('active', true),
            new EqualsFilter('salesChannels.id', $event->getSalesChannelContext()->getSalesChannelId())
        );

        $paymentMethods = $this->paymentMethodRepository->search($criteria, $event->getContext())->getEntities();

        $activeHandlerIdentifiers = [];
        foreach ($paymentMethods as $paymentMethod) {
            $activeHandlerIdentifiers[$paymentMethod->getHandlerIdentifier()] = true;
        }

        return $activeHandlerIdentifiers;
    }

    /**
     * @param list<AppFeature<CookieConfig>> $features
     */
    private function hasPaymentMethodConditions(array $features): bool
    {
        foreach ($features as $feature) {
            if ($feature->config->activePaymentMethods !== []) {
                return true;
            }

            foreach ($feature->config->entries as $entry) {
                if (self::entryPaymentMethods($entry) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $activePaymentMethods
     * @param array<string, true> $activeHandlerIdentifiers
     */
    private function isCookieAllowed(string $appName, array $activePaymentMethods, array $activeHandlerIdentifiers): bool
    {
        if ($activePaymentMethods === []) {
            return true;
        }

        foreach ($activePaymentMethods as $identifier) {
            if ($identifier === self::ANY_PAYMENT_METHOD) {
                $prefix = AppHandlerIdentifier::build($appName, '');
                foreach (array_keys($activeHandlerIdentifiers) as $handlerIdentifier) {
                    if (str_starts_with($handlerIdentifier, $prefix)) {
                        return true;
                    }
                }

                continue;
            }

            if (isset($activeHandlerIdentifiers[AppHandlerIdentifier::build($appName, $identifier)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return list<string>
     */
    private static function entryPaymentMethods(array $entry): array
    {
        /** @var list<string> */
        return $entry['active_payment_methods'] ?? [];
    }
}
