<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 * This class contains some defaults for test case
 */
#[Package('core')]
class TestDefaults
{
    final public const SALES_CHANNEL = '98432def39fc4624b33213a56b8c944d';
    final public const FALLBACK_CUSTOMER_GROUP = 'cfbd5018d38d41d8adca10d94fc8bdd6';
    // use pre-hashed password, so we don't need to hash in every test, password is `shopware`
    final public const HASHED_PASSWORD = '$2y$10$XFRhv2TdOz9GItRt6ZgHl.e/HpO5Mfea6zDNXI9Q8BasBRtWbqSTS';
}
