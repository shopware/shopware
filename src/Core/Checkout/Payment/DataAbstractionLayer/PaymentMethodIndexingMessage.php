<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

/**
 * @package checkout
 */
#[Package('checkout')]
class PaymentMethodIndexingMessage extends EntityIndexingMessage
{
}
