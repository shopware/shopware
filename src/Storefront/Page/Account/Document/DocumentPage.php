<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Document;

use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Storefront\Page\Page;

class DocumentPage extends Page
{
    /**
     * @var GeneratedDocument
     */
    protected $document;

    /**
     * @var string|null
     */
    protected $deepLinkCode;

    public function getDeepLinkCode(): ?string
    {
        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(?string $deepLinkCode): void
    {
        $this->deepLinkCode = $deepLinkCode;
    }

    public function getDocument(): GeneratedDocument
    {
        return $this->document;
    }

    public function setDocument(GeneratedDocument $document): void
    {
        $this->document = $document;
    }
}
