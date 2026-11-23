<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    description: 'Part of the legacy document generation pipeline. DocumentV2 handles this concern internally and exposes no counterpart.',
)]
class DocumentIdStruct extends Struct
{
    public function __construct(
        protected string $id,
        protected string $deepLinkCode,
        protected ?string $mediaId = null,
        protected ?string $a11yMediaId = null,
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

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function getA11yMediaId(): ?string
    {
        return $this->a11yMediaId;
    }

    public function getApiAlias(): string
    {
        return 'document_id';
    }
}
