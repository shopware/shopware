<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\Request;

class FallbackUrlPackage extends UrlPackage
{
    public function __construct($baseUrls, VersionStrategyInterface $versionStrategy)
    {
        $baseUrls = iterator_to_array($this->applyFallback($baseUrls), false);
        parent::__construct($baseUrls, $versionStrategy, null);
    }

    private function applyFallback(array $baseUrls): iterable
    {
        $request = Request::createFromGlobals();
        $basePath = $request->getSchemeAndHttpHost() . $request->getBasePath();
        $requestUrl = rtrim($basePath, '/') . '/';

        if ($request->getHost() === '' && isset($_SERVER['APP_URL'])) {
            $requestUrl = $_SERVER['APP_URL'];
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
