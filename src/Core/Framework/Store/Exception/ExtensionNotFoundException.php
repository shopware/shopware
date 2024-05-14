<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;

#[Package('checkout')]
class ExtensionNotFoundException extends StoreException
{
}
