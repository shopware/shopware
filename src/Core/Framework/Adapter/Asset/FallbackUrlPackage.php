<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\Request;

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
        VersionStrategyInterface $versionStrategy
    ) {
        $baseUrls = iterator_to_array($this->applyFallback($baseUrls), false);
        parent::__construct($baseUrls, $versionStrategy);
    }

    private function applyFallback(array $baseUrls): \Generator
    {
        $request = Request::createFromGlobals();
        $basePath = $request->getSchemeAndHttpHost() . $request->getBasePath();
        $requestUrl = rtrim($basePath, '/') . '/';

        if ($request->getHost() === '' && EnvironmentHelper::getVariable('APP_URL')) {
            $requestUrl = EnvironmentHelper::getVariable('APP_URL');
        }

        foreach ($baseUrls as $url) {
            if ($url === '') {
                yield $requestUrl;
            } else {
                yield $url;
            }
        }
    }
}
