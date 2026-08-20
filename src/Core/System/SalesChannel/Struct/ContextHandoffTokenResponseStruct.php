<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ContextHandoffTokenResponseStruct extends Struct
{
    public function __construct(
        public readonly string $token,
        public readonly string $expiresAt,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'context_handoff_token';
    }
}
