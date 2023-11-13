<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Util;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;

#[Package('core')]
class AccessKeyHelper
{
    private const USER_IDENTIFIER = 'SWUA';
    private const INTEGRATION_IDENTIFIER = 'SWIA';
    private const SALES_CHANNEL_IDENTIFIER = 'SWSC';
    private const PRODUCT_EXPORT_IDENTIFIER = 'SWPE';

    /**
     * @var array<string, string>
     */
    public static $mapping = [
        self::USER_IDENTIFIER => 'user',
        self::INTEGRATION_IDENTIFIER => 'integration',
        self::SALES_CHANNEL_IDENTIFIER => 'sales-channel',
        self::PRODUCT_EXPORT_IDENTIFIER => 'product-export',
    ];

    public static function generateAccessKey(string $identifier): string
    {
        return self::getIdentifier($identifier) . mb_strtoupper(str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(Random::getAlphanumericString(16))));
    }

    public static function generateSecretAccessKey(): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(Random::getAlphanumericString(38)));
    }

    public static function getOrigin(string $accessKey): string
    {
        $identifier = mb_substr($accessKey, 0, 4);

        if (!isset(self::$mapping[$identifier])) {
            throw ApiException::invalidAccessKey();
        }

        return self::$mapping[$identifier];
    }

    private static function getIdentifier(string $origin): string
    {
        $mapping = array_flip(self::$mapping);

        if (!isset($mapping[$origin])) {
            throw ApiException::invalidAccessKeyIdentifier();
        }

        return $mapping[$origin];
    }
}
