<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class AppCookieProvider implements CookieProviderInterface, CookieCollectionProviderInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly CookieProviderInterface|CookieCollectionProviderInterface $inner,
        private readonly EntityRepository $appRepository,
        private readonly CookieService $cookieService
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use getCookieGroupCollection() instead.
     *
     * @return array<string|int, mixed>
     */
    public function getCookieGroups(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use getCookieGroupCollection() instead')
        );
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true),
            new NotEqualsFilter('app.cookies', null)
        );

        $result = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities();

        if ($this->inner instanceof CookieProviderInterface) {
            $cookies = array_values($this->inner->getCookieGroups());
        } else {
            // Convert typed base to legacy array for BC path
            $cookies = $this->groupCollectionToLegacyArray($this->inner->getCookieGroupCollection());
        }

        if ($result->count() === 0) {
            return $cookies;
        }

        return $this->mergeCookies($cookies, $result);
    }

    public function getCookieGroupCollection(): CookieGroupCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true),
            new NotEqualsFilter('app.cookies', null)
        );

        $apps = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities();

        $base = $this->inner instanceof CookieCollectionProviderInterface
            ? $this->inner->getCookieGroupCollection()
            // $this->inner->getCookieGroups() need to be changed to $this->inner->getCookieGroupCollection() for 6.8.0
            : $this->cookieService->getCookieGroupCollection($this->inner->getCookieGroups(), null, translate: false);

        if ($apps->count() === 0) {
            return $base;
        }

        $appCookies = [];
        foreach ($apps as $app) {
            foreach ($app->getCookies() as $cookie) {
                $appCookies[] = $cookie;
            }
        }

        $appsCollection = $this->cookieService->getCookieGroupCollection($appCookies, null, translate: false);

        return $this->mergeTypedCollections($base, $appsCollection);
    }

    /**
     * merges cookie groups by the snippet name of the group
     * and only iterates once over every cookie
     *
     * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use mergeTypedCollections() instead.
     *
     * @param array<string|int, mixed> $cookies
     *
     * @return array<string|int, mixed>
     */
    private function mergeCookies(array $cookies, AppCollection $apps): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use mergeTypedCollections() instead')
        );
        $cookieGroups = [];
        // build an array with the snippetName of a cookie group and the index in the cookies array
        // this way we need to iterate only once over the cookies
        foreach ($cookies as $index => $cookie) {
            if (\array_key_exists('entries', $cookie)) {
                $cookieGroups[$cookie['snippet_name']] = $index;
            }
        }

        foreach ($apps as $app) {
            foreach ($app->getCookies() as $cookie) {
                // cookies that are not part of a group can simply be added to the cookies array
                if (!\array_key_exists('entries', $cookie)) {
                    $cookies[] = $cookie;

                    continue;
                }

                // if a cookie group with the same name already exists in the cookies array
                // we merge the entries of both cookie groups
                if (\array_key_exists($cookie['snippet_name'], $cookieGroups)) {
                    $originalIndex = $cookieGroups[$cookie['snippet_name']];
                    $cookies[$originalIndex]['entries'] = array_merge(
                        $cookies[$originalIndex]['entries'],
                        $cookie['entries']
                    );

                    continue;
                }

                // if no group with that name exists we add the cookie group to the cookies array
                // and add the snippet name and the index to the snippet group array
                $cookies[] = $cookie;
                $cookieGroups[$cookie['snippet_name']] = \count($cookies) - 1;
            }
        }

        return $cookies;
    }

    private function mergeTypedCollections(CookieGroupCollection $base, CookieGroupCollection $apps): CookieGroupCollection
    {
        $indexBySnippet = [];
        foreach ($base as $i => $group) {
            if ($group->snippetName !== null) {
                $indexBySnippet[$group->snippetName] = $i;
            }
        }

        foreach ($apps as $appGroup) {
            $snippet = $appGroup->snippetName ?? null;
            $hasEntries = \count($appGroup->entries) > 0;

            if ($snippet !== null && $hasEntries && isset($indexBySnippet[$snippet])) {
                $baseGroup = $base->get($indexBySnippet[$snippet]);
                if ($baseGroup !== null) {
                    $baseGroup->entries = array_merge($baseGroup->entries, $appGroup->entries);
                }
                continue;
            }

            $base->add($appGroup);
            if ($snippet !== null) {
                $indexBySnippet[$snippet] = $base->count() - 1;
            }
        }

        return $base;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use getCookieGroupCollection() instead of legacy arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupCollectionToLegacyArray(CookieGroupCollection $collection): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use getCookieGroupCollection() instead')
        );
        $out = [];
        foreach ($collection as $group) {
            $entryArrays = [];
            foreach ($group->entries as $entry) {
                $entryArrays[] = [
                    'snippet_name' => $entry->snippetName ?? null,
                    'snippet_description' => $entry->snippetDescription ?? null,
                    'cookie' => $entry->cookie ?? null,
                    'value' => $entry->value ?? null,
                    'expiration' => $entry->expiration ?? null,
                    'hidden' => $entry->hidden ?? false,
                ];
            }

            $out[] = [
                'isRequired' => $group->isRequired,
                'snippet_name' => $group->snippetName ?? null,
                'snippet_description' => $group->snippetDescription ?? null,
                'cookie' => $group->cookie ?? null,
                'value' => $group->value ?? null,
                'expiration' => $group->expiration ?? null,
                'entries' => $entryArrays,
            ];
        }

        return $out;
    }
}
