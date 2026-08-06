<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentDefinitionProvider;
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
        $registry = new ConsentDefinitionRegistry([$backendData, $productAnalytics], []);

        static::assertSame([
            'backend_data' => $backendData,
            'product_analytics' => $productAnalytics,
        ], $registry->all());
    }

    public function testGetReturnsDefinitionByName(): void
    {
        $backendData = new TestDefinition('backend_data', 'system');
        $registry = new ConsentDefinitionRegistry([$backendData], []);

        static::assertSame($backendData, $registry->get('backend_data'));
    }

    public function testGetThrowsIfDefinitionDoesNotExist(): void
    {
        $registry = new ConsentDefinitionRegistry([], []);

        $this->expectExceptionObject(ConsentException::notFound('backend_data'));

        $registry->get('backend_data');
    }

    public function testAllIncludesProvidedDefinitions(): void
    {
        $backendData = new TestDefinition('backend_data', 'system');
        $appConsent = new TestDefinition('MyApp-data_sharing', 'system');
        $registry = new ConsentDefinitionRegistry([$backendData], [$this->provider($appConsent)]);

        static::assertSame([
            'MyApp-data_sharing' => $appConsent,
            'backend_data' => $backendData,
        ], $registry->all());
        static::assertSame($appConsent, $registry->get('MyApp-data_sharing'));
    }

    public function testProvidedDefinitionCannotReplaceTaggedDefinition(): void
    {
        $backendData = new TestDefinition('backend_data', 'system');
        $registry = new ConsentDefinitionRegistry(
            [$backendData],
            [$this->provider(new TestDefinition('backend_data', 'admin_user'))]
        );

        static::assertSame(['backend_data' => $backendData], $registry->all());
    }

    public function testResetCollectsFromProvidersAgain(): void
    {
        $first = new TestDefinition('MyApp-first', 'system');
        $second = new TestDefinition('MyApp-second', 'system');

        $provider = static::createStub(ConsentDefinitionProvider::class);
        $provider->method('getConsentDefinitions')->willReturnOnConsecutiveCalls([$first], [$second]);

        $registry = new ConsentDefinitionRegistry([], [$provider]);

        static::assertSame(['MyApp-first' => $first], $registry->all());
        static::assertSame(['MyApp-first' => $first], $registry->all());

        $registry->reset();

        static::assertSame(['MyApp-second' => $second], $registry->all());
    }

    private function provider(ConsentDefinition ...$definitions): ConsentDefinitionProvider
    {
        $provider = static::createStub(ConsentDefinitionProvider::class);
        $provider->method('getConsentDefinitions')->willReturn(array_values($definitions));

        return $provider;
    }
}
