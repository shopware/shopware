<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentCollection;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class ProductDescriptionReviewsStruct extends Struct
{
    protected ?string $productId = null;

    protected ?bool $ratingSuccess = null;

    protected ?ProductReviewResult $reviews = null;

    protected ?SalesChannelProductEntity $product = null;

    protected ?ProductDocumentCollection $documents = null;

    protected ?MediaCollection $documentMedia = null;

    public function getProduct(): ?SalesChannelProductEntity
    {
        return $this->product;
    }

    public function setProduct(SalesChannelProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getDocuments(): ?ProductDocumentCollection
    {
        return $this->documents;
    }

    public function setDocuments(ProductDocumentCollection $documents): void
    {
        $this->documents = $documents;
    }

    public function getDocumentMedia(): ?MediaCollection
    {
        return $this->documentMedia;
    }

    public function setDocumentMedia(MediaCollection $documentMedia): void
    {
        $this->documentMedia = $documentMedia;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getReviews(): ?ProductReviewResult
    {
        return $this->reviews;
    }

    public function setReviews(ProductReviewResult $result): void
    {
        $this->reviews = $result;
    }

    public function getRatingSuccess(): ?bool
    {
        return $this->ratingSuccess;
    }

    public function setRatingSuccess(bool $rateSuccess): void
    {
        $this->ratingSuccess = $rateSuccess;
    }

    public function getApiAlias(): string
    {
        return 'cms_product_description_reviews';
    }
}
