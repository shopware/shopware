<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Log\Package;

class_exists(\Shopware\Core\Checkout\DocumentV2\DocumentCollection::class);

if (!class_exists(DocumentCollection::class, false)) {
    /**
     * @deprecated tag:v6.9.0 - compatibility alias, this file is deleted together with document generation v1.
     * Use \Shopware\Core\Checkout\DocumentV2\DocumentCollection instead.
     */
    #[Package('after-sales')]
    class DocumentCollection extends \Shopware\Core\Checkout\DocumentV2\DocumentCollection
    {
    }
}
