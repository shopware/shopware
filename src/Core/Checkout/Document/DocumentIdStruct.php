<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @package customer-order
 */
class DocumentIdStruct extends Struct
{
    protected string $id;

    protected string $deepLinkCode;

    protected ?string $mediaId;

    public function __construct(string $id, string $deepLinkCode, ?string $mediaId = null)
    {
        $this->id = $id;
        $this->deepLinkCode = $deepLinkCode;
        $this->mediaId = $mediaId;
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

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function getApiAlias(): string
    {
        return 'document_id';
    }
}
