<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso\TokenService\_fixtures;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class JwksIds
{
    /**
     * These keys are the "kid" in the "jwks.json" see in this directory
     */
    public const KEY_ID_ONE = 'b16b070d-28e4-4759-9c51-d43730dda8fa';
    public const KEY_ID_TWO = '742be0d0-038a-4f1a-b70d-d1ecabc2af05';
}
