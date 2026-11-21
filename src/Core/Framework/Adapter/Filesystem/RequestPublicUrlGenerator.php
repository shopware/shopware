<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\UrlGeneration\PrefixPublicUrlGenerator;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use Shopware\Core\Framework\Adapter\Asset\RequestBaseUrlResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * Public URL generator for filesystems without a configured URL.
 * It resolves the base URL on every call, so a filesystem reused across requests in long-running workers follows the current host.
 *
 * @internal
 */
#[Package('framework')]
final readonly class RequestPublicUrlGenerator implements PublicUrlGenerator
{
    public function __construct(private RequestBaseUrlResolver $baseUrlResolver)
    {
    }

    public function publicUrl(string $path, Config $config): string
    {
        return (new PrefixPublicUrlGenerator($this->baseUrlResolver->resolve() . '/'))->publicUrl($path, $config);
    }
}
