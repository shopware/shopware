<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Router;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Api\ResponseHook;
use Shopware\Core\Framework\Script\Api\ScriptResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @script-service custom_endpoint
 */
#[Package('core')]
class RouterService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ResponseHook $responseHook
    ) {
    }

    /**
     * @internal
     */
    public function generate(string|array $hook, array $query = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        if (\is_string($hook)) {
            $query['hook'] = $hook;
        } else {
            $query = $hook + $query;
        }
        if (!isset($query['hook'])) {
            $query['hook'] = $this->responseHook->getName();
        }
        $route = $query['_route'] ?? $this->requestStack->getCurrentRequest()?->attributes->get('_route');

        return $this->urlGenerator->generate($route, $query, $referenceType);
    }

    /**
     * Returns the absolute path to a script hook
     *
     * @param string|array $hook Hook name or query parameters. If no hook name is defined, the current hook will be used.
     * @param array<string, mixed> $query Query paramters as associative array.
     *
     * @return string Example: /api/app/hook-name?query
     */
    public function path(string|array $hook = null, array $query = []): string
    {
        return $this->generate($hook, $query, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Returns the absolute url to a script hook
     *
     * @param string|array $hook Hook name or query parameters. If no hook name is defined, the current hook will be used.
     * @param array<string, mixed> $query Query paramters as associative array.
     *
     * @return string Example: http://localhost/api/app/hook-name?query
     */
    public function url(string|array $hook = null, array $query = []): string
    {
        return $this->generate($hook, $query, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param string|array $hook Hook name or query parameters. If no hook name is defined, the current hook will be used.
     * @param array<string, mixed> $query Query paramters as associative array.
     * @param int $code HTTP status code
     */
    public function redirect(string|array $hook = null, array $query = [], int $code = Response::HTTP_FOUND): void
    {
        $url = $this->generate($hook, $query);

        $response = new ScriptResponse(
            new RedirectResponse($url, $code),
            $code
        );
        $this->responseHook->setResponse($response);
    }
}
