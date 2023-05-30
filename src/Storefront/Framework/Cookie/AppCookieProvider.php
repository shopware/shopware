<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AppCookieProvider implements CookieProviderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CookieProviderInterface $inner,
        private readonly EntityRepository $appRepository
    ) {
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getCookieGroups(): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true),
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('app.cookies', null),
                ]
            )
        );

        $result = $this->appRepository->search(
            $criteria,
            Context::createDefaultContext()
        );

        $cookies = array_values($this->inner->getCookieGroups());

        if ($result->count() < 1) {
            return $cookies;
        }

        return $this->mergeCookies($cookies, $result);
    }

    /**
     * merges cookie groups by the snippet name of the group
     * and only iterates once over every cookie
     *
     * @param array<string|int, mixed> $cookies
     *
     * @return array<string|int, mixed>
     */
    private function mergeCookies(array $cookies, EntitySearchResult $apps): array
    {
        $cookieGroups = [];
        // build an array with the snippetName of a cookie group and the index in the cookies array
        // this way we need to iterate only once over the cookies
        foreach ($cookies as $index => $cookie) {
            if (\array_key_exists('entries', $cookie)) {
                $cookieGroups[$cookie['snippet_name']] = $index;
            }
        }

        /** @var AppEntity $app */
        foreach ($apps->getEntities() as $app) {
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
}
