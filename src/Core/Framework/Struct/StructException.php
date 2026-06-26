<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Exception\AssignException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

#[Package('framework')]
class StructException extends HttpException
{
    private const ASSIGN_TYPE_ERROR = 'FRAMEWORK__STRUCT__ASSIGN_TYPE_ERROR';
    private const CREATE_FROM_ERROR = 'FRAMEWORK__STRUCT__CREATE_FROM_ERROR';
    private const NORMALIZE_ERROR = 'FRAMEWORK__STRUCT__NORMALIZE_ERROR';

    public static function assignTypeError(\TypeError $error): self
    {
        return new AssignException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ASSIGN_TYPE_ERROR,
            $error->getMessage(),
            ['typeError' => $error],
            $error,
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function createFromError(string $message): self|InvalidArgumentException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new InvalidArgumentException($message);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CREATE_FROM_ERROR,
            $message,
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function normalizeError(string $message): self|InvalidArgumentException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new InvalidArgumentException($message);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NORMALIZE_ERROR,
            $message,
        );
    }
}
