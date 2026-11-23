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

        $publicMediaExtensions = array_flip($this->mediaFileExtensionListProvider->getAllowedExtensions(false, $context));

        return array_values(array_filter(
            self::normalize($event->getAllowedExtensions()),
            static fn (string $extension): bool => isset($publicMediaExtensions[$extension]),
        ));
    }

    /**
     * @param array<array-key, mixed> $extensions
     *
     * @return list<string>
     */
    private static function normalize(array $extensions): array
    {
        $normalized = [];
        foreach ($extensions as $extension) {
            $extension = mb_strtolower(ltrim(trim((string) $extension), '.'));
            if ($extension !== '') {
                $normalized[$extension] = true;
            }
        }

        return array_map(strval(...), array_keys($normalized));
    }
}
