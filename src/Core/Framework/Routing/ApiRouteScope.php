<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class ApiRouteScope extends AbstractRouteScope implements ApiContextRouteScopeDependant
{
    final public const ID = 'api';
    final public const ALLOWED_PATH = 'api';

    protected array $allowedPaths = [self::ALLOWED_PATH, 'sw-domain-hash.html'];

    public function isAllowed(Request $request): bool
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        $authRequired = $request->attributes->get('auth_required', true);
        if (!$context instanceof Context) {
            throw RoutingException::missingRouteAttribute(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, (string) $request->attributes->get('_route', ''));
        }
        $source = $context->getSource();

        if (!$authRequired) {
            return $source instanceof SystemSource || $source instanceof AdminApiSource;
        }

        return $context->getSource() instanceof AdminApiSource;
    }

    /**
     * @codeCoverageIgnore no logic
     */
    public function getId(): string
    {
        return self::ID;
    }
}
