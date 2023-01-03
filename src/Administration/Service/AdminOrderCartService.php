<?php declare(strict_types=1);

namespace Shopware\Administration\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Checkout\Cart\ApiOrderCartService;

/**
 * @deprecated tag:v6.5.0 will be removed used @see ApiOrderCartService directly
 */
#[Package('administration')]
class AdminOrderCartService extends ApiOrderCartService
{
}
