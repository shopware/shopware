<?php declare(strict_types=1);

namespace Shopware\Core\Framework\NumberRange;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class NoConfigurationException extends ShopwareHttpException
{
    protected $code = 'NO-NUMBER-RANGE-CONFIGURATION';

    public function __construct(string $entityName, string $salesChannelId = null, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            'No number range configuration found for entity "%s" with salesChannelId "%s".',
            $entityName,
            $salesChannelId
        );

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
