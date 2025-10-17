<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('framework')]
class CookieRoute extends AbstractCookieRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CookieProvider $cookieProvider,
    ) {
    }

    public function getDecorated(): AbstractCookieRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/cookie-groups', name: 'store-api.cookie.groups', methods: [Request::METHOD_GET])]
    public function getCookieGroups(Request $request, SalesChannelContext $salesChannelContext): CookieRouteResponse
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups($request, $salesChannelContext);
        $hash = $this->generateCookieConfigurationHash($cookieGroups);

        return new CookieRouteResponse($cookieGroups, $hash);
    }

    /**
     * We use explicit properties to make hash generation robust against object extensions.
     */
    private function generateCookieConfigurationHash(CookieGroupCollection $cookieGroups): string
    {
        $hashData = [];

        $groups = array_values($cookieGroups->getElements());
        usort($groups, static function (CookieGroup $a, CookieGroup $b): int {
            return strcmp($a->getTechnicalName(), $b->getTechnicalName());
        });

        foreach ($groups as $cookieGroup) {
            $groupData = [
                'technicalName' => $cookieGroup->getTechnicalName(),
                'isRequired' => $cookieGroup->isRequired,
                'description' => $cookieGroup->description ?? null,
                'value' => $cookieGroup->value ?? null,
                'expiration' => $cookieGroup->expiration ?? null,
                'name' => $cookieGroup->name,
                'cookie' => $cookieGroup->getCookie(),
            ];

            $groupData['entries'] = null;
            $cookieEntries = $cookieGroup->getEntries();
            if ($cookieEntries !== null) {
                $entries = array_values($cookieEntries->getElements());
                usort($entries, static function (CookieEntry $a, CookieEntry $b): int {
                    return strcmp($a->cookie, $b->cookie);
                });

                $entriesData = [];
                foreach ($entries as $cookieEntry) {
                    $entriesData[] = [
                        'cookie' => $cookieEntry->cookie,
                        'value' => $cookieEntry->value ?? null,
                        'expiration' => $cookieEntry->expiration ?? null,
                        'name' => $cookieEntry->name ?? null,
                        'description' => $cookieEntry->description ?? null,
                        'hidden' => $cookieEntry->hidden,
                    ];
                }
                $groupData['entries'] = $entriesData;
            }

            $hashData[] = $groupData;
        }

        try {
            return Hasher::hash($hashData);
        } catch (UtilException $e) {
            throw CookieException::hashGenerationFailed('Cookie configuration processing failed: ' . $e->getMessage());
        }
    }
}
