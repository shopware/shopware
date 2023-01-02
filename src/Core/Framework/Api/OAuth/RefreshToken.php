<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Shopware\Core\Framework\Log\Package;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

/**
 * @package core
 */
#[Package('core')]
class RefreshToken implements RefreshTokenEntityInterface
{
    use RefreshTokenTrait;
    use EntityTrait;
}
