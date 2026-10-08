<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Result of a presigned upload request: the media id, the opaque token to hand back at confirm, and the instructions
 * for the direct-to-remote-storage upload. The client sends `headers` verbatim so it never has to reproduce the
 * server's Content-Type canonicalisation.
 */
#[Package('discovery')]
readonly class PresignedUploadTicket
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $mediaId,
        public string $uploadToken,
        public string $method,
        public string $url,
        public array $headers,
        public string $expiresAt,
    ) {
    }
}
