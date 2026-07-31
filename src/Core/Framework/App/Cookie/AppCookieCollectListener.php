<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cookie;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-import-type Cookie from AppEntity
 */
#[Package('framework')]
class AppCookieCollectListener
{
    private const ANY_PAYMENT_METHOD = '*';

    private const APP_HANDLER_PREFIX = 'app\\';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly EntityRepository $paymentMethodRepository,
    ) {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true),
            new NotEqualsFilter('app.cookies', null)
        );

        $apps = $this->appRepository->search($criteria, $event->getContext())->getEntities();

        $activeHandlerIdentifiers = $this->fetchActiveHandlerIdentifiers($apps, $event);

        foreach ($apps as $app) {
            $cookies = $this->filterCookies($app->getName(), $app->getCookies(), $activeHandlerIdentifiers);

            $this->addCookies($event->cookieGroupCollection, $cookies);
        }
    }

    /**
     * @param list<Cookie> $appCookies
     */
    private function addCookies(CookieGroupCollection $cookieGroupCollection, array $appCookies): void
    {
        foreach ($appCookies as $cookie) {
            $cookieGroup = $cookieGroupCollection->get($cookie['snippet_name']);
            if ($cookieGroup === null) {
                $cookieGroup = new CookieGroup($cookie['snippet_name']);
                $cookieGroupCollection->add($cookieGroup);
            }

            if (\array_key_exists('snippet_description', $cookie)) {
                $cookieGroup->description = $cookie['snippet_description'];
            }

            if (\array_key_exists('cookie', $cookie)) {
                $cookieGroup->setCookie($cookie['cookie']);
            }

            if (\array_key_exists('value', $cookie)) {
                $cookieGroup->value = $cookie['value'];
            }

            if (\array_key_exists('expiration', $cookie)) {
                $cookieGroup->expiration = (int) $cookie['expiration'];
            }

            if (\array_key_exists('entries', $cookie)) {
                $cookieEntries = $cookieGroup->getEntries();
                if ($cookieEntries === null) {
                    $cookieEntries = new CookieEntryCollection();
                    $cookieGroup->setEntries($cookieEntries);
                }

                foreach ($cookie['entries'] as $entry) {
                    $cookieEntry = new CookieEntry($entry['cookie']);

                    if (\array_key_exists('snippet_name', $entry)) {
                        $cookieEntry->name = $entry['snippet_name'];
                    }

                    if (\array_key_exists('snippet_description', $entry)) {
                        $cookieEntry->description = $entry['snippet_description'];
                    }

                    if (\array_key_exists('value', $entry)) {
                        $cookieEntry->value = $entry['value'];
                    }

                    if (\array_key_exists('expiration', $entry)) {
                        $cookieEntry->expiration = (int) $entry['expiration'];
                    }

                    $cookieEntries->add($cookieEntry);
                }
            }
        }
    }

    /**
     * @return array<string, true> handler identifiers of the app payment methods active in the sales channel
     */
    private function fetchActiveHandlerIdentifiers(AppCollection $apps, CookieGroupCollectEvent $event): array
    {
        if (!$this->hasPaymentMethodConditions($apps)) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new PrefixFilter('handlerIdentifier', self::APP_HANDLER_PREFIX),
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

    private function hasPaymentMethodConditions(AppCollection $apps): bool
    {
        foreach ($apps as $app) {
            foreach ($app->getCookies() as $cookie) {
                foreach ([$cookie, ...($cookie['entries'] ?? [])] as $item) {
                    if (($item['active_payment_methods'] ?? []) !== []) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param list<Cookie> $cookies
     * @param array<string, true> $activeHandlerIdentifiers
     *
     * @return list<Cookie>
     */
    private function filterCookies(string $appName, array $cookies, array $activeHandlerIdentifiers): array
    {
        $filtered = [];

        foreach ($cookies as $cookie) {
            if (!$this->isCookieAllowed($appName, $cookie, $activeHandlerIdentifiers)) {
                continue;
            }

            if (\array_key_exists('entries', $cookie)) {
                $cookie['entries'] = array_values(array_filter(
                    $cookie['entries'],
                    fn (array $entry): bool => $this->isCookieAllowed($appName, $entry, $activeHandlerIdentifiers),
                ));
            }

            $filtered[] = $cookie;
        }

        return $filtered;
    }

    /**
     * @param array{active_payment_methods?: list<string>} $cookie
     * @param array<string, true> $activeHandlerIdentifiers
     */
    private function isCookieAllowed(string $appName, array $cookie, array $activeHandlerIdentifiers): bool
    {
        $identifiers = $cookie['active_payment_methods'] ?? [];
        if ($identifiers === []) {
            return true;
        }

        foreach ($identifiers as $identifier) {
            if ($identifier === self::ANY_PAYMENT_METHOD) {
                $prefix = self::buildHandlerIdentifier($appName, '');
                foreach (array_keys($activeHandlerIdentifiers) as $handlerIdentifier) {
                    if (str_starts_with($handlerIdentifier, $prefix)) {
                        return true;
                    }
                }

                continue;
            }

            if (isset($activeHandlerIdentifiers[self::buildHandlerIdentifier($appName, $identifier)])) {
                return true;
            }
        }

        return false;
    }

    private static function buildHandlerIdentifier(string $appName, string $identifier): string
    {
        // must match PaymentMethodLifecycleHandler
        return \sprintf('%s%s_%s', self::APP_HANDLER_PREFIX, $appName, $identifier);
    }
}
