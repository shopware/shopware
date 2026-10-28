<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

/**
 * Marks events whose emails must use metadata-only logging.
 */
#[Package('framework')]
interface SensitiveMailAware extends MailAware
{
}
