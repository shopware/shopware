<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cookie;

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
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-import-type Cookie from AppEntity
 */
#[Package('framework')]
class AppCookieCollectListener
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
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

        foreach ($apps as $app) {
            $this->addCookies($event->cookieGroupCollection, $app->getCookies());
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
}
