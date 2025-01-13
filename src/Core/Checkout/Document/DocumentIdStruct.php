<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('checkout')]
class DocumentIdStruct extends Struct
{
    /**
     * @deprecated tag:v6.7.0 - $mediaId will be removed.
     */
    public function __construct(
        protected string $id,
        protected string $deepLinkCode,
        protected ?string $mediaId = null,
    ) {
    }

    public function getDeepLinkCode(): string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @deprecated tag:v6.7.0 - $mediaId will be removed.
     */
    public function getMediaId(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'Property `media` will be removed'
        );

        return $this->mediaId;
    }

    public function getApiAlias(): string
    {
        return 'document_id';
    }
}
