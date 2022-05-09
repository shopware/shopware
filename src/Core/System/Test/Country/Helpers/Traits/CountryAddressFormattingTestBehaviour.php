<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Country\Helpers\Traits;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

trait CountryAddressFormattingTestBehaviour
{
    use BasicTestDataBehaviour;

    protected function setUseAdvancedFormatForCountry(Connection $conn): void
    {
        $conn->executeUpdate('UPDATE `country` SET `use_default_address_format` = 0 WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($this->getValidCountryId()),
        ]);
    }

    protected function setAdvancedAddressFormatPlainForCountry(Connection $conn, string $template): void
    {
        $conn->executeUpdate('UPDATE `country_translation` SET `advanced_address_format_plain` = :template WHERE country_id = :id', [
            'id' => Uuid::fromHexToBytes($this->getValidCountryId()),
            'template' => $template,
        ]);
    }
}
