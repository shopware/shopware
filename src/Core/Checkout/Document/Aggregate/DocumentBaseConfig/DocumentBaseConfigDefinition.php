<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Shopware\Core\Framework\Log\Package;

class_exists(\Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition::class);

if (!class_exists(DocumentBaseConfigDefinition::class, false)) {
    /**
     * @deprecated tag:v6.9.0 - compatibility alias, this file is deleted together with document generation v1.
     * Use \Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition instead.
     */
    #[Package('after-sales')]
    class DocumentBaseConfigDefinition extends \Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition
    {
    }
}
