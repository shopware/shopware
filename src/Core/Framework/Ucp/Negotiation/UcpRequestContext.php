<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Negotiation;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\CapabilityIntersection;
use Shopware\Core\Framework\Ucp\Capability\Signals\SignalsExtractor;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Transport\Embedded\EmbeddedSession;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Per-request UCP context. Attached to the Symfony Request via attributes
 * and read by capability controllers, the envelope listener, and the exception
 * listener.
 */
#[Package('framework')]
final class UcpRequestContext
{
    public const REQUEST_ATTRIBUTE = '_ucp_context';

    /**
     * Request attribute set to `true` after the inbound RFC 9421 signature
     * was successfully verified against the platform's JWKS — and to `false`
     * otherwise (signature_policy `off`, `log` with failure, or no signature
     * present). Components like {@see SignalsExtractor}
     * MUST gate trust on this flag.
     */
    public const ATTR_SIGNATURE_VERIFIED = '_ucp_signature_verified';

    /**
     * Request attribute set to the verified {@see EmbeddedSession}
     * id when the inbound request originated from an iframe bridge.
     */
    public const ATTR_EMBEDDED_SESSION_ID = '_ucp_embedded_session_id';

    public function __construct(
        public readonly UcpSalesChannelConfigEntity $config,
        public readonly SalesChannelContext $salesChannelContext,
        public readonly CapabilityIntersection $intersection,
        public readonly string $platformProfileUri,
    ) {
    }
}
