<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Event;

use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Symfony\Contracts\EventDispatcher\Event;

class ProductExportChangeEncodingEvent extends Event
{
    public const NAME = 'product_export.change_encoding';

    /**
     * @var ProductExportEntity
     */
    private $productExportEntity;

    /**
     * @var string
     */
    private $content;

    /**
     * @var string
     */
    private $encodedContent;

    public function __construct(ProductExportEntity $productExportEntity, string $content, string $encodedContent)
    {
        $this->productExportEntity = $productExportEntity;
        $this->content = $content;
        $this->encodedContent = $encodedContent;
    }

    public function getProductExportEntity(): ProductExportEntity
    {
        return $this->productExportEntity;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getEncodedContent(): string
    {
        return $this->encodedContent;
    }

    public function setEncodedContent(string $encodedContent): void
    {
        $this->encodedContent = $encodedContent;
    }
}
