<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Dispatched immediately after a UCP request body is parsed but **before** the
 * controller performs any side effects. Listeners may:
 *
 *   - read the parsed `$payload` to perform extension-specific validation
 *     (e.g. AP2 signature checks);
 *   - call {@see reject()} with a UCP message object to abort the request
 *     with a `422 Unprocessable Entity` response.
 *
 * Listeners **MUST NOT** mutate the payload — that's intentionally read-only
 * to keep the request canonical for signature verification.
 *
 * @final
 */
#[Package('framework')]
class UcpCheckoutRequestEvent extends Event
{
    /**
     * @var array{type: string, code: string, content: string, severity: string}|null
     */
    private ?array $rejection = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $checkoutId,
        public readonly array $payload,
        public readonly SalesChannelContext $salesChannelContext,
        public readonly UcpRequestContext $ucpContext,
    ) {
    }

    /**
     * Abort the checkout. The rejection object is included in the error
     * response under `messages[]`. `code` SHOULD be a UCP-style snake_case
     * identifier (e.g. `ap2_mandate_invalid`); `severity` is typically
     * `unrecoverable`.
     */
    public function reject(string $code, string $content, string $severity = 'unrecoverable'): void
    {
        $this->rejection = [
            'type' => 'error',
            'code' => $code,
            'content' => $content,
            'severity' => $severity,
        ];
    }

    public function isRejected(): bool
    {
        return $this->rejection !== null;
    }

    /**
     * @return array{type: string, code: string, content: string, severity: string}|null
     */
    public function getRejection(): ?array
    {
        return $this->rejection;
    }
}
