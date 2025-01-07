<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 * This class contains some defaults for test case
 */
#[Package('core')]
class TestDefaults
{
    final public const TOKEN = 'test-token';
    final public const DOMAIN = 'test-domain';
    final public const SALES_CHANNEL = '98432def39fc4624b33213a56b8c944d';
    final public const NAVIGATION_CATEGORY = 'f8466865cc6a45e48ed98dd2f6a0a293';
    final public const TAX_CALCULATION_TYPE = SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL;
    final public const CUSTOMER_GROUP = 'cfbd5018d38d41d8adca10d94fc8bdd6';
    final public const CUSTOMER_GROUP_DISPLAY_GROSS = true;
    final public const TAX = 'c725e107825c4c7281673aeea66ed67e';
    final public const TAX_RATE = 19.0;
    final public const PAYMENT_METHOD = 'cce0e1ca23de4c55868ce057f628c349';
    final public const SHIPPING_METHOD = '37dbe80c5cbb4852a97cb742ed04ba41';
    final public const COUNTRY = 'd4eb3205dd9444169b3f60c056c313a1';
    final public const COUNTRY_STATE = '119d6e30fc4f468daa88ff5b413e9322';
    final public const CUSTOMER_ADDRESS = '08f1594313494c3e9eb57bb53486fe61';
    final public const CUSTOMER = '42d58aa78cf14851968a786a66bab93a';
    /**
     * @deprecated tag:v6.7.0 - Will be removed use {@see \Shopware\Core\Test\TestDefaults::CUSTOMER_GROUP} instead
     */
    final public const FALLBACK_CUSTOMER_GROUP = 'cfbd5018d38d41d8adca10d94fc8bdd6';
    // use pre-hashed password, so we don't need to hash in every test, password is `shopware`
    final public const HASHED_PASSWORD = '$2y$10$XFRhv2TdOz9GItRt6ZgHl.e/HpO5Mfea6zDNXI9Q8BasBRtWbqSTS';
}
