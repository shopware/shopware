<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class Migration1739355802FixVatHandling extends MigrationStep
{
    /**
     * @return array<string, string>
     */
    private const VAT_PATTERNS = [
        'BE' => 'BE\d{10}',
        'GR' => '(EL|GR)\d{9}',
        'HR' => 'HR\d{11}',
        'IE' => 'IE(\d[A-Z0-9]\d{5}[A-Za-z]$|^\d{7}[A-W][A-I])', // pre and post 2013 pattern
        'LT' => 'LT(\d{9}|\d{12})',
        'RO' => 'RO(?!0)\d{1,10}',
    ];

    public function getCreationTimestamp(): int
    {
        return 1739355802;
    }

    public function update(Connection $connection): void
    {
        /** @var array<string, string> $countries */
        $countries = $connection->fetchAllKeyValue(
            'SELECT `id`, `iso` FROM `country` WHERE `iso` IN (:isos)',
            ['isos' => \array_keys(self::VAT_PATTERNS)],
            ['isos' => ArrayParameterType::STRING]
        );

        foreach ($countries as $id => $iso) {
            $connection->update('country', ['vat_id_pattern' => self::VAT_PATTERNS[$iso]], ['id' => $id]);
        }
    }
}
