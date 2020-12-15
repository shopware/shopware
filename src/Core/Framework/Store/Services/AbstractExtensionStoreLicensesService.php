<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Store\Struct\LicenseCollection;
use Shopware\Core\Framework\Store\Struct\ReviewStruct;

abstract class AbstractExtensionStoreLicensesService
{
    abstract public function getLicensedExtensions(Context $context): LicenseCollection;

    abstract public function purchaseExtension(int $extensionId, int $variantId, Context $context): void;

    abstract public function cancelSubscription(int $licenseId, Context $context): LicenseCollection;

    abstract public function rateLicensedExtension(ReviewStruct $rating, Context $context): void;

    abstract protected function getDecorated(): AbstractExtensionStoreLicensesService;
}
