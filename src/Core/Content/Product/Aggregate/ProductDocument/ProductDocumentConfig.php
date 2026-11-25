<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Shopware\Core\Content\Product\Events\ProductDocumentAllowedExtensionsEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
readonly class ProductDocumentConfig
{
    /**
     * @param list<string> $allowedExtensions
     * @param int $maxFileSize in bytes
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private array $allowedExtensions,
        public int $maxFileSize,
        private MediaFileExtensionListProvider $mediaFileExtensionListProvider,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getAllowedExtensions(Context $context): array
    {
        $event = new ProductDocumentAllowedExtensionsEvent($this->allowedExtensions, $context);
        $this->eventDispatcher->dispatch($event);

        return array_map(strval(...), array_values(array_intersect(
            $this->mediaFileExtensionListProvider->getAllowedExtensions(false, $context),
            array_map(
                static fn (string|int $extension): string => mb_strtolower(ltrim(trim((string) $extension), '.')),
                $event->getAllowedExtensions(),
            ),
        )));
    }
}
