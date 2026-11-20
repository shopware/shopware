<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type\CmsElementDataPreparation;
use Shopware\Core\Framework\Log\Package;

require_once __DIR__ . '/data/CmsElements/Resolvers.php';

/**
 * @internal
 */
#[Package('framework')]
class CmsElementDataPreparationTest extends TestCase
{
    public function testDiscoversRegisteredResolversInDispatchOrderWithoutExecutingThem(): void
    {
        $manifest = CmsElementDataPreparation::discover(__DIR__ . '/data/CmsElements/container.xml');

        static::assertCount(7, $manifest['resolvers']);
        static::assertStringEndsWith('\\CmsFixtures\\ReplacementResolver', $manifest['resolvers'][0]['class']);
        static::assertStringEndsWith('\\CmsFixtures\\HelperResolver', $manifest['resolvers'][6]['class']);
        static::assertSame(__NAMESPACE__ . '\\CmsFixtures\\BaseResolver::enrich', $manifest['resolvers'][1]['enrich']);
        static::assertSame(['unused', 'input'], $manifest['methods'][__NAMESPACE__ . '\\CmsFixtures\\HelperResolver::populate']['parameters']);
        static::assertSame(['input'], $manifest['methods'][__NAMESPACE__ . '\\CmsFixtures\\HelperResolver::populate']['slots']);
    }
}
