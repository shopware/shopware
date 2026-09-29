<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 *
 * Update existing `product_export.body_template` rows whose content still
 * matches the unmodified OOTB admin starter templates (Google, Idealo,
 * Billiger) so the rendered `<g:product_type>` / breadcrumb honours the
 * configured main / SEO category. Customer-modified templates are not
 * affected — the WHERE clause requires a byte-exact match against the
 * shipped snapshot.
 */
#[Package('inventory')]
class Migration1780029093FixProductComparisonTemplateBreadcrumb extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780029093;
    }

    public function update(Connection $connection): void
    {
        $filesystem = new Filesystem();
        $fixtures = __DIR__ . '/../Fixtures/productComparison-export-profiles/issue-12852/';

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        // Match the admin starter template registration in
        // src/Administration/.../product-export-templates/{vendor}/index.js — Idealo
        // and Billiger send `body.trim()` to the DAL, Google sends the body as-is.
        // We must apply the same normalisation here, otherwise the byte-exact WHERE
        // clause silently misses every Idealo / Billiger row.
        foreach ([
            ['google.xml', false],
            ['idealo.csv', true],
            ['billiger.csv', true],
        ] as [$name, $trim]) {
            [$base, $ext] = explode('.', $name);
            $old = $filesystem->readFile($fixtures . $base . '_old.' . $ext . '.twig');
            $new = $filesystem->readFile($fixtures . $base . '_new.' . $ext . '.twig');

            if ($trim) {
                $old = trim($old);
                $new = trim($new);
            }

            $connection->update(
                'product_export',
                ['body_template' => $new, 'updated_at' => $now],
                ['body_template' => $old],
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // intentionally empty
    }
}
