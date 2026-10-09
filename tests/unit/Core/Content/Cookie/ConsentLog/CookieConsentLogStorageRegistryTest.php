<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ConsentLog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentLogStorageRegistry;
use Shopware\Core\Content\Cookie\ConsentLog\NullCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieConsentLogStorageRegistry::class)]
class CookieConsentLogStorageRegistryTest extends TestCase
{
    public function testItResolvesTheConfiguredStorage(): void
    {
        $storage = new NullCookieConsentLogStorage();
        $registry = new CookieConsentLogStorageRegistry(
            new ServiceLocator(['none' => static fn () => $storage, 'database' => static fn () => new NullCookieConsentLogStorage()]),
            'none',
        );

        static::assertSame($storage, $registry->getStorage());
    }

    public function testItNamesTheAvailableStoragesWhenTheConfiguredOneIsMissing(): void
    {
        $registry = new CookieConsentLogStorageRegistry(
            new ServiceLocator(['database' => static fn () => new NullCookieConsentLogStorage(), 'none' => static fn () => new NullCookieConsentLogStorage()]),
            's3',
        );

        $this->expectExceptionObject(CookieException::consentLogStorageNotFound('s3', ['database', 'none']));

        $registry->getStorage();
    }
}
