<?php declare(strict_types=1);

namespace Shopware\Core;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
/**
 * @deprecated tag:v6.7.0 - Will be removed in 6.7.0. as it is unused
 */
class HttpKernelResult
{
    public function __construct(
        protected Request $request,
        protected Response $response
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'),
        );
    }

    public function getRequest(): Request
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'),
        );

        return $this->request;
    }

    public function getResponse(): Response
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0'),
        );

        return $this->response;
    }
}
