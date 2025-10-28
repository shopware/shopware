<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class DevOpsException extends HttpException
{
    public static function invalidArgumentException(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            'DEV_OPS__INVALID_ARGUMENT',
            $message,
        );
    }
}
