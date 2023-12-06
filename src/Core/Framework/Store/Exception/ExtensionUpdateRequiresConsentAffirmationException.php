<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;

#[Package('services-settings')]
class ExtensionUpdateRequiresConsentAffirmationException extends StoreException
{
}
