<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Repro;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 *
 * DEMO wiring smoke for the `direct` executor — used ONLY by the workflow's dry-run
 * demo (dry_run=true, demo_layer=direct). Not a real reproduction.
 */
class ReproTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCoreServiceIsAvailable(): void
    {
        // Healthy: the product repository resolves from the container. Passing => the
        // executor maps OK => not_reproduced, which proves the whole direct path runs:
        // provision -> place test under tests/integration/Repro -> phpunit -> parse -> report.
        static::assertNotNull(static::getContainer()->get('product.repository'));
    }
}
