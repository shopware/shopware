<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @package core
 */
class JsonApiResponse extends JsonResponse
{
    public function update(): static
    {
        parent::update();

        $this->headers->set('Content-Type', 'application/vnd.api+json');

        return $this;
    }
}
