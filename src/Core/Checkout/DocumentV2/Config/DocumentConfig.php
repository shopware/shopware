<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Config;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentConfig
{
    /**
     * @param array<string, string> $filenameInfixes
     */
    public function __construct(
        public string $pageSize,
        public string $pageOrientation,
        public int $itemsPerPage,
        public ?string $filenamePrefix = null,
        public ?string $filenameSuffix = null,
        public array $filenameInfixes = [],
        public ?MediaEntity $logo = null,
    ) {
    }

    public function buildFileStem(string $documentNumber, string $format): string
    {
        $infix = $this->filenameInfixes[$format] ?? null;

        return ($this->filenamePrefix ?? '')
            . $documentNumber
            . ($infix ?? '')
            . ($this->filenameSuffix ?? '');
    }
}
