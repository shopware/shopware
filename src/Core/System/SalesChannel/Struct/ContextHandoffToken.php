<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Struct;

use Shopware\Core\Framework\JWT\Struct\JWTStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * Signed reference to an existing sales channel context.
 *
 * The referenced context token is never a claim of this token, the `jti` is the lookup key for it.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ContextHandoffToken extends JWTStruct
{
    public ?string $salesChannelId = null;
}
