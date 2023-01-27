<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class NoConfigurationException extends ShopwareHttpException
{
    public function __construct(
        string $entityName,
        ?string $salesChannelId = null
    ) {
        parent::__construct(
            'No number range configuration found for entity "{{ entity }}" with sales channel "{{ salesChannelId }}".',
            ['entity' => $entityName, 'salesChannelId' => $salesChannelId]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__NO_NUMBER_RANGE_CONFIGURATION';
    }
}
