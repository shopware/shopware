<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class UtilException extends HttpException
{
    public const INVALID_JSON = 'UTIL_INVALID_JSON';
    public const INVALID_JSON_NOT_LIST = 'UTIL_INVALID_JSON_NOT_LIST';

    public static function invalidJson(\JsonException $e): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_JSON,
            'JSON is invalid',
            [],
            $e
        );
    }

    public static function invalidJsonNotList(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_JSON_NOT_LIST,
            'JSON cannot be decoded to a list'
        );
    }
}
