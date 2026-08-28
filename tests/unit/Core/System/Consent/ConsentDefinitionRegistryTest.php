<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinitionRegistry;
use Shopware\Core\System\Consent\ConsentException;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentDefinitionRegistry::class)]
class ConsentDefinitionRegistryTest extends TestCase
{
    public function testAllReturnsDefinitionsKeyedByName(): void
    {
        $backendData = new TestDefinition('backend_data', 'system');
        $productAnalytics = new TestDefinition('product_analytics', 'admin_user');
        $registry = new ConsentDefinitionRegistry([$backendData, $productAnalytics]);

        static::assertSame([
            'backend_data' => $backendData,
            'product_analytics' => $productAnalytics,
        ], $registry->all());
    }

    public function testGetReturnsDefinitionByName(): void
    {
        $backendData = new TestDefinition('backend_data', 'system');
        $registry = new ConsentDefinitionRegistry([$backendData]);

        static::assertSame($backendData, $registry->get('backend_data'));
    }

    public function testGetThrowsIfDefinitionDoesNotExist(): void
    {
        $registry = new ConsentDefinitionRegistry([]);

        $this->expectExceptionObject(ConsentException::notFound('backend_data'));

        $registry->get('backend_data');
    }
}
