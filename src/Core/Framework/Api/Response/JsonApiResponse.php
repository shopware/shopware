<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Package('core')]
class JsonApiResponse extends JsonResponse
{
    protected function update(): static
    {
        parent::update();

        $this->headers->set('Content-Type', 'application/vnd.api+json');

        return $this;
    }
}
