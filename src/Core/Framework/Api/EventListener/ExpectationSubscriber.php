<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Composer\InstalledVersions;
use Composer\Semver\Semver;
use Shopware\Core\Framework\Api\Exception\ExpectationFailedException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @phpstan-type PluginData array{'composerName': string, 'active': bool, 'version': string}
 */
#[Package('core')]
class ExpectationSubscriber implements EventSubscriberInterface
{
    private const SHOPWARE_CORE_PACKAGES = [
        'shopware/platform',
        'shopware/core',
        'shopware/administration',
        'shopware/elasticsearch',
        'shopware/storefront',
    ];

    /**
     * @internal
     *
     * @param list<PluginData> $plugins
     */
    public function __construct(
        private readonly string $shopwareVersion,
        private readonly array $plugins
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['checkExpectations', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST],
        ];
    }

    public function checkExpectations(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE)) {
            return;
        }

        /** @var list<string> $scope */
        $scope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\in_array(ApiRouteScope::ID, $scope, true)) {
            return;
        }

        $expectations = $this->checkPackages($request);

        if (\count($expectations)) {
            throw new ExpectationFailedException($expectations);
        }
    }

    /**
     * @return list<string>
     */
    private function checkPackages(Request $request): array
    {
        // swag/plugin1:~6.1,swag/plugin2:~6.1
        $extensionConstraints = array_filter(explode(',', (string) $request->headers->get(PlatformRequest::HEADER_EXPECT_PACKAGES)));
        if ($extensionConstraints === []) {
            return [];
        }

        $plugins = $this->getIndexedPackages();

        $fails = [];

        foreach ($extensionConstraints as $extension) {
            $explode = explode(':', $extension);
            if (\count($explode) !== 2) {
                $fails[] = sprintf('Got invalid string: "%s"', $extension);

                continue;
            }

            $name = $explode[0];
            $constraint = $explode[1];

            if (isset($plugins[$name])) {
                $installedVersion = $plugins[$name];
            } else {
                try {
                    $installedVersion = InstalledVersions::getPrettyVersion($name);
                } catch (\OutOfBoundsException) {
                    $fails[] = sprintf('Requested package: %s is not available', $name);

                    continue;
                }

                if (\in_array($name, self::SHOPWARE_CORE_PACKAGES, true)) {
                    $installedVersion = $this->shopwareVersion;
                }
            }

            if ($installedVersion === null) {
                // should never happen, but phpstan would complain otherwise
                continue;
            }

            if (Semver::satisfies($installedVersion, $constraint)) {
                continue;
            }

            $fails[] = sprintf('Version constraint for %s is failed. Installed is: %s', $name, $installedVersion);
        }

        return $fails;
    }

    /**
     * Plugins are not in the InstalledPackages file until now
     *
     * @return array<string, string>
     */
    private function getIndexedPackages(): array
    {
        $versions = [];

        foreach ($this->plugins as $plugin) {
            if (!$plugin['active']) {
                continue;
            }

            $versions[$plugin['composerName']] = $plugin['version'];
        }

        return $versions;
    }
}
