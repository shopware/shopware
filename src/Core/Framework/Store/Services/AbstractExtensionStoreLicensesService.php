<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\ReviewStruct;

/**
 * @internal
 */
#[Package('merchant-services')]
abstract class AbstractExtensionStoreLicensesService
{
    abstract public function cancelSubscription(int $licenseId, Context $context): void;

    abstract public function rateLicensedExtension(ReviewStruct $rating, Context $context): void;

    abstract protected function getDecorated(): AbstractExtensionStoreLicensesService;
}
