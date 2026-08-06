<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\TaggedConsentDefinitionProvider;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(TaggedConsentDefinitionProvider::class)]
class TaggedConsentDefinitionProviderTest extends TestCase
{
    public function testProvidesTheRegisteredDefinitions(): void
    {
        $backendData = new TestDefinition('backend_data', 'system');
        $productAnalytics = new TestDefinition('product_analytics', 'admin_user');

        $provider = new TaggedConsentDefinitionProvider(new \ArrayIterator([$backendData, $productAnalytics]));

        static::assertSame([$backendData, $productAnalytics], $provider->getConsentDefinitions());
    }

    public function testProvidesNothingWithoutRegisteredDefinitions(): void
    {
        static::assertSame([], (new TaggedConsentDefinitionProvider([]))->getConsentDefinitions());
    }
}
