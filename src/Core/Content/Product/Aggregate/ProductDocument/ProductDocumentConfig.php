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
     */
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private array $allowedExtensions,
        public int $maxFileSize,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getAllowedExtensions(Context $context): array
    {
        $event = new ProductDocumentAllowedExtensionsEvent($this->allowedExtensions, $context);
        $this->eventDispatcher->dispatch($event);

        $extensions = [];
        foreach ($event->getAllowedExtensions() as $extension) {
            $extension = mb_strtolower(ltrim(trim((string) $extension), '.'));
            if ($extension !== '') {
                $extensions[$extension] = true;
            }
        }

        return array_map(strval(...), array_keys($extensions));
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
}
