<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\SalesChannel;

use Shopware\Core\Framework\Log\Package;

/*
 * Loading the surviving class registers the alias for this name. The declaration below is never reached, it only
 * makes the name visible to IDEs and static analysis.
 */
class_exists(\Shopware\Core\Checkout\DocumentV2\SalesChannel\AbstractDocumentRoute::class);

if (!class_exists(AbstractDocumentRoute::class, false)) {
    /**
     * @deprecated tag:v6.9.0 - compatibility alias, this file is deleted together with document generation v1.
     * Use \Shopware\Core\Checkout\DocumentV2\SalesChannel\AbstractDocumentRoute instead.
     */
    #[Package('after-sales')]
    abstract class AbstractDocumentRoute extends \Shopware\Core\Checkout\DocumentV2\SalesChannel\AbstractDocumentRoute
    {
    }
}
