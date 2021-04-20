<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidSettingValueException extends ShopwareHttpException
{
    public function __construct(string $key, ?string $neededType = null, ?string $actualType = null, ?\Throwable $previous = null)
    {
        $message = "Invalid value for '{{ key }}'";
        if ($neededType !== null) {
            $message .= ". Must be of type '{{ neededType }}'";
        }
        if ($actualType !== null) {
            $message .= ". But is of type '{{ actualType }}'";
        }

        parent::__construct($message, [
            'key' => $key,
            'neededType' => $neededType,
            'actualType' => $actualType,
        ], $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__INVALID_SETTING_VALUE';
    }
}
