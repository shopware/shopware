<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
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
    /**
     * @param array<string> $allowedExtensions
     * @param list<string> $privateAllowedExtensions
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private array $allowedExtensions,
        private array $privateAllowedExtensions,
    ) {
    }

    public function validate(string $extension, bool $isPrivate, Context $context, string $mediaId = ''): void
    {
        $event = new MediaFileExtensionWhitelistEvent(
            $isPrivate ? $this->privateAllowedExtensions : $this->allowedExtensions,
            $context,
        );
        $this->eventDispatcher->dispatch($event);

        $fileExtension = mb_strtolower($extension);

        foreach ($event->getWhitelist() as $allowed) {
            if ($fileExtension === mb_strtolower((string) $allowed)) {
                return;
            }
        }

        throw MediaException::fileExtensionNotSupported($mediaId, $fileExtension);
    }
}
