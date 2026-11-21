<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the base URL for assets and files that have no configured URL.
 * Long-running workers reuse services across requests, so callers resolve it on every use instead of storing the result.
 *
 * @internal
 */
#[Package('framework')]
final readonly class RequestBaseUrlResolver
{
    public function __construct(private ?RequestStack $requestStack = null)
    {
    }

    /**
     * Returns scheme, host and base path of the main request without a trailing slash.
     * Outside of a request, for example on the CLI, it returns `APP_URL`.
     */
    public function resolve(): string
    {
        $request = $this->requestStack?->getMainRequest() ?? new Request(server: $_SERVER);

        if ($request->getHost() === '') {
            return rtrim((string) EnvironmentHelper::getVariable('APP_URL'), '/');
        }

        return rtrim($request->getSchemeAndHttpHost() . $request->getBasePath(), '/');
    }
}
