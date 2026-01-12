<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Request;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @final
 */
#[Package('framework')]
class RequestParamHelper
{
    /**
     * Restriction:
     *
     * Use this helper only in case the controller action supports more than one method
     *
     * e.g.
     * #[Route(
     *      path: '/product/{productId}/reviews',
     *      name: 'frontend.product.reviews',
     *      defaults: ['XmlHttpRequest' => true],
     *      methods: [Request::METHOD_GET, Request::METHOD_POST]
     * )]
     *
     * else use the proper request properties
     * e.g.
     * $value = $request->attributes->get('some_key');
     * $value = $request->query->get('some_key');
     * $value = $request->request->get('some_key');
     */
    public static function get(Request $request, string $name, mixed $default = null): mixed
    {
        if ($request->query->has($name)) {
            return $request->query->get($name, $default);
        }

        if ($request->request->has($name)) {
            return $request->request->get($name, $default);
        }

        return $default;
    }
}
