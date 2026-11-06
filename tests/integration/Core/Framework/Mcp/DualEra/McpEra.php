<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\DualEra;

use Mcp\Schema\Enum\ProtocolVersion;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum McpEra
{
    /**
     * The client sends `initialize` first and then the `Mcp-Session-Id` it got on every request.
     */
    case Handshake;

    /**
     * There is no session. Every request carries its protocol version and client capabilities in `params._meta`.
     */
    case Stateless;

    /**
     * The protocol version the test client speaks in this era.
     */
    public function protocolVersion(): ProtocolVersion
    {
        return match ($this) {
            self::Handshake => ProtocolVersion::V2025_11_25,
            self::Stateless => ProtocolVersion::V2026_07_28,
        };
    }
}
