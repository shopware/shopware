<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * Generates absolute URLs for Administration and API routes.
 * Uses the request host when trusted hosts are configured, otherwise the APP_URL.
 * Storefront routes are not supported, as their URLs depend on the sales channel domain.
 *
 * @internal
 */
#[Package('framework')]
final class TrustedOriginUrlGenerator
{
    /**
     * @param array<string, string> $parameters
     */
    public static function generate(RouterInterface $router, string $name, array $parameters = []): string
    {
        if (Request::getTrustedHosts() !== []) {
            return $router->generate($name, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $requestContext = $router->getContext();
        $router->setContext(RequestContext::fromUri(self::getAppUrl()));

        try {
            return $router->generate($name, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        } finally {
            $router->setContext($requestContext);
        }
    }

    public static function generateAdministrationUrl(RouterInterface $router): string
    {
        if (Request::getTrustedHosts() === []) {
            return self::buildAdministrationUrlFromAppUrl();
        }

        try {
            return $router->generate('administration.index', [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (RouteNotFoundException) {
            // fallback if admin bundle is not installed, the url should work once the bundle is installed
            return self::buildAdministrationUrlFromAppUrl();
        }
    }

    private static function buildAdministrationUrlFromAppUrl(): string
    {
        $pathName = trim((string) EnvironmentHelper::getVariable('SHOPWARE_ADMINISTRATION_PATH_NAME', 'admin'), '/');

        return self::getAppUrl() . '/' . $pathName;
    }

    private static function getAppUrl(): string
    {
        $appUrl = rtrim((string) EnvironmentHelper::getVariable('APP_URL', ''), '/');

        if (!filter_var($appUrl, \FILTER_VALIDATE_URL) || !\in_array(parse_url($appUrl, \PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw RoutingException::invalidAppUrl($appUrl);
        }

        return $appUrl;
    }
}
