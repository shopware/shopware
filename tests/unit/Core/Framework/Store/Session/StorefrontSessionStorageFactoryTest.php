<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Session\StorefrontSessionStorageFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;

/**
 * @internal
 */
#[CoversClass(StorefrontSessionStorageFactory::class)]
class StorefrontSessionStorageFactoryTest extends TestCase
{
    #[DataProvider('sessionStorageProvider')]
    public function testCreateStorageSetsCookiePath(
        ?string $baseUrl,
        string $expectedCookiePath
    ): void {
        $storageMock = $this->getMockBuilder(NativeSessionStorage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $storageMock->expects($this->once())
            ->method('setOptions')
            ->with(['cookie_path' => $expectedCookiePath]);

        $innerFactory = $this->createMock(SessionStorageFactoryInterface::class);
        $innerFactory->expects($this->once())->method('createStorage')->willReturn($storageMock);

        $factory = new StorefrontSessionStorageFactory($innerFactory);

        $request = new Request();
        $request->attributes->set('sw-sales-channel-base-url', $baseUrl);

        $factory->createStorage($request);
    }

    /**
     * @return iterable<string, array{baseUrl: string|null, expectedCookiePath: string}>
     */
    public static function sessionStorageProvider(): iterable
    {
        yield 'Specific sales channel path' => [
            'baseUrl' => '/germany',
            'expectedCookiePath' => '/germany',
        ];

        yield 'Empty string defaults to root' => [
            'baseUrl' => '',
            'expectedCookiePath' => '/',
        ];

        yield 'Null value defaults to root' => [
            'baseUrl' => null,
            'expectedCookiePath' => '/',
        ];
    }
}
