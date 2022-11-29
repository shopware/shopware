<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Document;

use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Core\Framework\Feature;
use Shopware\Storefront\Page\Page;

/**
 * @package customer-order
 *
 * @deprecated tag:v6.5.0 - Will be removed
 */
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->deepLinkCode;
    }

    public function setDeepLinkCode(?string $deepLinkCode): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $this->deepLinkCode = $deepLinkCode;
    }

    public function getDocument(): GeneratedDocument
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->document;
    }

    public function setDocument(GeneratedDocument $document): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $this->document = $document;
    }
}
