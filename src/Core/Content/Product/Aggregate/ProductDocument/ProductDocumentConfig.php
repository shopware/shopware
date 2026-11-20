<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Shopware\Core\Content\Product\Events\ProductDocumentAllowedExtensionsEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\MemorySizeCalculator;
use Symfony\Component\Mime\MimeTypes;
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
     * @param list<string> $publicMediaExtensions
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private array $allowedExtensions,
        public int $maxFileSize,
        private array $publicMediaExtensions,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getAllowedExtensions(Context $context): array
    {
        $event = new ProductDocumentAllowedExtensionsEvent($this->allowedExtensions, $context);
        $this->eventDispatcher->dispatch($event);

        $publicMediaExtensions = array_flip(self::normalize($this->publicMediaExtensions));

        return array_values(array_filter(
            self::normalize($event->getAllowedExtensions()),
            static fn (string $extension): bool => isset($publicMediaExtensions[$extension]),
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    public function getMimeTypesByExtension(Context $context): array
    {
        $mimeTypes = MimeTypes::getDefault();

        $mimeTypesByExtension = [];
        foreach ($this->getAllowedExtensions($context) as $extension) {
            $mimeTypesByExtension[$extension] = array_values(array_unique($mimeTypes->getMimeTypes($extension)));
        }

        return $mimeTypesByExtension;
    }

    public function getMaxUploadFileSize(): int
    {
        return MemorySizeCalculator::getMaxUploadSize($this->maxFileSize);
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
