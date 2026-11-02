<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Extension\CookieRouteExtension;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
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
 *
 * @phpstan-type EntryData array{cookie: string, value: ?string, expiration: ?int, name: ?string, description: ?string, hidden: bool}
 * @phpstan-type GroupData array{technicalName: string, isRequired: bool, description: ?string, value: ?string, expiration: ?int, name: string, cookie: ?string, entries: list<EntryData>|null}
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CookieRoute extends AbstractCookieRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CookieProvider $cookieProvider,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractCookieRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/cookie-groups', name: 'store-api.cookie.groups', methods: [Request::METHOD_GET])]
    public function getCookieGroups(Request $request, SalesChannelContext $salesChannelContext): CookieRouteResponse
    {
        return $this->extensions->publish(
            name: CookieRouteExtension::NAME,
            extension: new CookieRouteExtension($request, $salesChannelContext),
            function: $this->_getCookieGroups(...),
        );
    }

    private function _getCookieGroups(Request $request, SalesChannelContext $salesChannelContext): CookieRouteResponse
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups($request, $salesChannelContext);
        $configurationData = $this->getConfigurationData($cookieGroups);
        $hash = $this->hash($configurationData);
        $cookieHashes = $this->generateCookieHashes($configurationData);
        $this->setCookieConfigHashValue($cookieGroups, $hash);

        return new CookieRouteResponse($cookieGroups, $hash, $salesChannelContext->getLanguageId(), $cookieHashes);
    }

    /**
     * Builds the sorted configuration data both the configuration hash and the cookie hashes are generated from.
     * We use explicit properties to make hash generation robust against object extensions.
     *
     * @return list<GroupData>
     */
    private function getConfigurationData(CookieGroupCollection $cookieGroups): array
    {
        $configurationData = [];

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

            $configurationData[] = $groupData;
        }

        return $configurationData;
    }

    /**
     * Hashes the configuration data of each cookie together with the data of every group listing it.
     * This allows clients to detect which cookies were added or changed when the configuration hash changes.
     *
     * @param list<GroupData> $configurationData
     *
     * @return array<string, string>
     */
    private function generateCookieHashes(array $configurationData): array
    {
        $cookieData = [];

        foreach ($configurationData as $groupData) {
            $entries = $groupData['entries'] ?? [];
            unset($groupData['entries']);

            if ($groupData['cookie'] !== null) {
                $cookieData[$groupData['cookie']][] = ['group' => $groupData, 'entry' => null];
            }

            foreach ($entries as $entryData) {
                $cookieData[$entryData['cookie']][] = ['group' => $groupData, 'entry' => $entryData];
            }
        }

        ksort($cookieData, \SORT_STRING);

        return array_map($this->hash(...), $cookieData);
    }

    private function hash(mixed $data): string
    {
        try {
            return Hasher::hash($data);
        } catch (UtilException $e) {
            throw CookieException::hashGenerationFailed('Cookie configuration processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Sets the cookie-config-hash entry value to the generated hash for output purposes.
     */
    private function setCookieConfigHashValue(CookieGroupCollection $cookieGroups, string $hash): void
    {
        $cookie = $cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED)?->getEntries()?->get(CookieProvider::COOKIE_ENTRY_CONFIG_HASH_COOKIE);
        if ($cookie) {
            $cookie->value = $hash;
        }
    }
}
