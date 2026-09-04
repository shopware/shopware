<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DataLoaderProvider::class)]
class DataLoaderProviderTest extends TestCase
{
    #[TestDox('returns registered loader by type')]
    public function testGetReturnsRegisteredLoader(): void
    {
        $loader = static::createStub(AbstractContentDataLoader::class);
        $locator = new ServiceLocator(['entity' => fn () => $loader]);

        $provider = new DataLoaderProvider($locator);

        static::assertSame($loader, $provider->get('entity'));
    }

    #[TestDox('throws when loader type is not registered')]
    public function testGetThrowsWhenLoaderNotRegistered(): void
    {
        $locator = new ServiceLocator([]);
        $provider = new DataLoaderProvider($locator);

        $this->expectExceptionObject(ContentSystemException::dataLoaderNotRegistered('unknown_type', 'unknown', 'unknown'));

        $provider->get('unknown_type');
    }
}
