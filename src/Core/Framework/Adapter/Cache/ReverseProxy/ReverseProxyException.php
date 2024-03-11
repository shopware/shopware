<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\ReverseProxy;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ReverseProxyException extends HttpException
{
    private const REVERSE_PROXY_CANNOT_BAN_URL = 'REVERSE_PROXY__CANNOT_BAN_URL';

    public static function cannotBanRequest(string $url, string $error, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REVERSE_PROXY_CANNOT_BAN_URL,
            'BAN request failed to {{ url }} failed with error: {{ error }}',
            ['url' => $url, 'error' => $error],
            $e
        );
    }
}
