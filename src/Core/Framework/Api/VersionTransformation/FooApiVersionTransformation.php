<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FooApiVersionTransformation implements ApiVersionTransformation
{
    public function getVersion(): int
    {
        return 10;
    }

    public function getRoute(): string
    {
        return 'api.action.product.foo';
    }

    public function transformRequest(Request $request): void
    {
        $request->request->set('someOldKey', 'someOldValue');
    }

    public function transformResponse(Response $response): void
    {
        $response->setContent(str_replace('a', 'e', $response->getContent()));
    }
}
