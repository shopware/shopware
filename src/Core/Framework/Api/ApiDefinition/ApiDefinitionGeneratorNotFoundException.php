<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 *
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed. Use {@see ApiException::apiDefinitionGeneratorNotFound} instead
 */
#[Package('framework')]
class ApiDefinitionGeneratorNotFoundException extends ShopwareHttpException
{
    public function __construct(string $format)
    {
        parent::__construct(
            'A definition generator for format "{{ format }}" was not found.',
            ['format' => $format]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__API_DEFINITION_GENERATOR_NOT_SUPPORTED';
    }
}
