<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Request;

use Shopware\Core\Framework\Feature;
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
     * $value = $request->attributes->get('productId');
     * $value = $request->query->get('some_key');
     * $value = $request->request->get('some_key');
     */
    public static function get(Request $request, string $name, mixed $default = null): mixed
    {
        if (!Feature::isActive('v6.8.0.0') && $request->attributes->has($name)) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Using `RequestParamHelper::get()` to access parameters in attribute bag is deprecated. Consider using `$request->attributes` directly or store the parameters in `$request->query` or `$request->request` bags.'
            );

            $request->attributes->get($name, $default);
        }

        if ($request->query->has($name)) {
            return $request->query->all()[$name];
        }

        if ($request->request->has($name)) {
            return $request->request->all()[$name];
        }

        return $default;
    }
}
