<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[Package('core')]
class FallbackUrlPackage extends UrlPackage
{
    /**
     * @internal
     *
     * @param string|string[] $baseUrls
     */
    public function __construct(
        string|array $baseUrls,
        VersionStrategyInterface $versionStrategy,
        private readonly ?RequestStack $requestStack = null
    ) {
        if (!\is_array($baseUrls)) {
            $baseUrls = (array) $baseUrls;
        }

        parent::__construct($this->applyFallback($baseUrls), $versionStrategy);
    }

    /**
     * @param string[] $baseUrls
     *
     * @return string[]
     */
    private function applyFallback(array $baseUrls): array
    {
        $request = $this->requestStack?->getMainRequest() ?? new Request([], [], [], [], [], $_SERVER);

        $basePath = $request->getSchemeAndHttpHost() . $request->getBasePath();
        $requestUrl = rtrim($basePath, '/') . '/';

        if ($request->getHost() === '' && EnvironmentHelper::hasVariable('APP_URL')) {
            $requestUrl = EnvironmentHelper::getVariable('APP_URL');
        }

        foreach ($baseUrls as &$url) {
            if ($url === '') {
                $url = $requestUrl;
            }
        }

        unset($url);

        return $baseUrls;
    }
}
