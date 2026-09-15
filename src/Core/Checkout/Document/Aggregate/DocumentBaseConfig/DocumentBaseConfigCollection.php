<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Shopware\Core\Framework\Log\Package;

class_exists(\Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection::class);

if (!class_exists(DocumentBaseConfigCollection::class, false)) {
    /**
     * @deprecated tag:v6.9.0 - compatibility alias, this file is deleted together with document generation v1.
     * Use \Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection instead.
     */
    #[Package('after-sales')]
    class DocumentBaseConfigCollection extends \Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection
    {
    }
}
