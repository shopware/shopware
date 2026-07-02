<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * Validates an upload's file extension against the configured whitelist, which subscribers to
 * MediaFileExtensionWhitelistEvent may modify at runtime. Shared by the legacy FileSaver flow and
 * the presigned-upload flow so both paths run the same validation semantics.
 */
#[Package('discovery')]
readonly class MediaFileExtensionValidator
{
    private MediaFileExtensionWhitelistProvider $whitelistProvider;

    /**
     * @param array<string>|null $allowedExtensions
     * @param list<string>|null $privateAllowedExtensions
     */
    public function __construct(
        EventDispatcherInterface|MediaFileExtensionWhitelistProvider $eventDispatcher,
        ?array $allowedExtensions = null,
        ?array $privateAllowedExtensions = null,
    ) {
        if ($eventDispatcher instanceof MediaFileExtensionWhitelistProvider) {
            $this->whitelistProvider = $eventDispatcher;

            return;
        }

        $this->whitelistProvider = new MediaFileExtensionWhitelistProvider(
            $eventDispatcher,
            $allowedExtensions ?? [],
            $privateAllowedExtensions ?? [],
        );
    }

    public function validate(string $extension, bool $isPrivate, Context $context, string $mediaId = ''): void
    {
        $fileExtension = mb_strtolower($extension);

        foreach ($this->whitelistProvider->getAllowedExtensions($isPrivate, $context) as $allowed) {
            if ($fileExtension === mb_strtolower((string) $allowed)) {
                return;
            }
        }

        throw MediaException::fileExtensionNotSupported($mediaId, $fileExtension);
    }
}
