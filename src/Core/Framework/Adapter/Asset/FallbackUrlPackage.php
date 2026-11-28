<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

#[Package('framework')]
class FallbackUrlPackage extends UrlPackage
{
    private readonly RequestBaseUrlResolver $baseUrlResolver;

    /**
     * @internal
     *
     * @param string|list<string> $baseUrls
     */
    public function __construct(
        string|array $baseUrls,
        VersionStrategyInterface $versionStrategy,
        ?RequestStack $requestStack = null
    ) {
        parent::__construct($baseUrls, $versionStrategy);

        $this->baseUrlResolver = new RequestBaseUrlResolver($requestStack);
    }

    /**
     * Empty base URLs are resolved per call, so a package reused across requests in long-running workers follows the current host
     */
    public function getBaseUrl(string $path): string
    {
        $baseUrl = parent::getBaseUrl($path);

        if ($baseUrl !== '') {
            return $baseUrl;
        }

        return $this->baseUrlResolver->resolve();
    }
}
