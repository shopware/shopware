<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Psr\Http\Message\RequestInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WebhookRequest
{
    /**
     * We keep a dedicated logging snapshot here instead of reading back from the PSR-7 request.
     * The request contains transport-specific state like the generated signature header and a stream body,
     * while the event log should persist the curated payload we prepared before handing it to the client.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed> $options Pre-built Guzzle request options (timeouts, auth middleware config)
     */
    public function __construct(
        public RequestInterface $request,
        public array $headers,
        public string $body,
        public int $timestamp,
        public array $options = [],
    ) {
    }
}
