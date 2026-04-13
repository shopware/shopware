<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class RouteBlocklistService
{
    public function __construct(
        private RouterInterface $router
    ) {
    }

    public function isPathBlocked(string $path): bool
    {
        $normalizedPath = '/' . trim($path, '/');

        if ($normalizedPath === '/') {
            return true;
        }

        $originalMethod = $this->router->getContext()->getMethod();
        try {
            $this->router->getContext()->setMethod(Request::METHOD_GET);
            $this->router->match($normalizedPath);
        } catch (ResourceNotFoundException) {
            // Resource not found means the route is completely unused
            return false;
        } catch (MethodNotAllowedException) {
            // Method not allowed means the route exists for other methods, e.g., as POST in the API
            return true;
        } finally {
            $this->router->getContext()->setMethod($originalMethod);
        }

        return true;
    }
}
