<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.5.0 - Use @see \Shopware\Core\Content\ProductExport\Event\ProductExportContentTypeEvent instead
 */
#[Package('storefront')]
class ProductExportContentTypeEvent extends Event
{
    /**
     * @var string
     */
    private $fileFormat;

    /**
     * @var string
     */
    private $contentType;

    public function __construct(string $fileFormat, string $contentType)
    {
        $this->fileFormat = $fileFormat;
        $this->contentType = $contentType;
    }

    public function getFileFormat(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'Shopware\Core\Content\ProductExport\Event\ProductExportContentTypeEvent')
        );

        return $this->fileFormat;
    }

    public function getContentType(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'Shopware\Core\Content\ProductExport\Event\ProductExportContentTypeEvent')
        );

        return $this->contentType;
    }

    public function setContentType(string $contentType): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'Shopware\Core\Content\ProductExport\Event\ProductExportContentTypeEvent')
        );

        $this->contentType = $contentType;
    }
}
