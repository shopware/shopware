<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
/**
 * @deprecated tag:v6.8.0 - Will be removed, use SystemConfigException::invalidKey() instead
 */
class InvalidKeyException extends SystemConfigException
{
    public function __construct(string $key)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', 'SystemConfigException::invalidKey()')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_KEY,
            'Invalid key \'{{ key }}\'',
            ['key' => $key]
        );
    }
}
