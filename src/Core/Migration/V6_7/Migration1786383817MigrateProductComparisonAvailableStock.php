<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * Rename the deprecated `product.availableStock` accessor to `product.stock` in every
 * stored product export template, which is removed with 6.8. Since 6.6 the value is a
 * mirror of `product.stock`, so the rendered output is unchanged.
 *
 * This rewrites merchant-edited templates as well, not only the shipped starter ones:
 * a template that keeps the old accessor stops rendering in 6.8, and the shipped bodies
 * have been changed twice without a migration already, so matching known snapshots
 * byte for byte misses most of the installed base.
 */
#[Package('inventory')]
class Migration1786383817MigrateProductComparisonAvailableStock extends MigrationStep
{
    private const DEPRECATED_ACCESSOR = 'product.availableStock';

    private const REPLACEMENT = 'product.stock';

    /**
     * Every Twig-bearing column of `product_export`.
     */
    private const TEMPLATE_COLUMNS = [
        'header_template',
        'body_template',
        'footer_template',
    ];

    public function getCreationTimestamp(): int
    {
        return 1786383817;
    }

    public function update(Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT `id`, `header_template`, `body_template`, `footer_template`
             FROM `product_export`
             WHERE `header_template` LIKE :needle
                OR `body_template` LIKE :needle
                OR `footer_template` LIKE :needle',
            ['needle' => '%' . self::DEPRECATED_ACCESSOR . '%']
        );

        if ($rows === []) {
            return;
        }

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($rows as $row) {
            $payload = [];

            foreach (self::TEMPLATE_COLUMNS as $column) {
                $template = $row[$column];

                if (!\is_string($template)) {
                    continue;
                }

                $renamed = self::rename($template);

                if ($renamed !== $template) {
                    $payload[$column] = $renamed;
                }
            }

            // The LIKE above also matches accessors the rename deliberately skips, such as
            // `myProduct.availableStock`, so a matched row can still end up unchanged.
            if ($payload === []) {
                continue;
            }

            $payload['updated_at'] = $now;

            $connection->update(
                'product_export',
                $payload,
                ['id' => $row['id']],
                ['id' => ParameterType::BINARY],
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // intentionally empty
    }

    /**
     * Word boundaries keep the rename to the `product` root variable the export templates
     * are rendered with. Without them `myproduct.availableStock` would become
     * `myproduct.stock` and `product.availableStockLevel` would become
     * `product.stockLevel`. An aliased variable (`{% set p = product %}{{ p.availableStock }}`)
     * cannot be recognised without parsing the template and stays untouched, see UPGRADE-6.8.md.
     */
    private static function rename(string $template): string
    {
        return (string) preg_replace(
            '/\b' . preg_quote(self::DEPRECATED_ACCESSOR, '/') . '\b/',
            self::REPLACEMENT,
            $template
        );
    }
}
