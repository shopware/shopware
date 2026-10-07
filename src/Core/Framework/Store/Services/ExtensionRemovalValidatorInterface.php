<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
interface ExtensionRemovalValidatorInterface
{
    /**
     * @throws StoreException when the extension is still in use and must not be removed
     */
    public function validateCanBeRemoved(string $technicalName, string $extensionId, Context $context): void;
}
