<?php declare(strict_types=1);

namespace Shopware\Rest\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class JsonApiResponse extends JsonResponse
{
    public function update(): void
    {
        parent::update();

        $this->headers->set('Content-Type', 'application/vnd.api+json');
    }
}
