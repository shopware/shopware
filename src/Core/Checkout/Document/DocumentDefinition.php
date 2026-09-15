<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\Log\Package;

class_exists(\Shopware\Core\Checkout\DocumentV2\DocumentDefinition::class);

if (!class_exists(DocumentDefinition::class, false)) {
    /**
     * @deprecated tag:v6.9.0 - compatibility alias, this file is deleted together with document generation v1.
     * Use \Shopware\Core\Checkout\DocumentV2\DocumentDefinition instead.
     */
    #[Package('after-sales')]
    class DocumentDefinition extends \Shopware\Core\Checkout\DocumentV2\DocumentDefinition
    {
    }
}
