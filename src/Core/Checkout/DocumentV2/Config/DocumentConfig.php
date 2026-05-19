<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Config;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class DocumentConfig
{
    public function __construct(
        public string $pageSize,
        public string $pageOrientation,
        public int $itemsPerPage,
        public ?string $filenamePrefix = null,
        public ?string $filenameSuffix = null,
        public ?MediaEntity $logo = null,
        public bool $displayHeader = false,
        public bool $displayFooter = false,
        public bool $displayPageCount = false,
        public bool $displayCompanyAddress = false,
        public bool $displayReturnAddress = false,
        public bool $displayCustomerVatId = false,
    ) {
    }

    public function buildFileStem(string $documentNumber): string
    {
        return ($this->filenamePrefix ?? '') . $documentNumber . ($this->filenameSuffix ?? '');
    }
}
