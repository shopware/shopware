<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Session\SessionFactory;
use Shopware\Core\Framework\Adapter\Session\StatefulFlashBag;
use Shopware\Core\Framework\Test\TestSessionStorageFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * @internal
 */
#[CoversClass(SessionFactory::class)]
class SessionFactoryTest extends TestCase
{
    public function testFactory(): void
    {
        $factory = new SessionFactory(
            new RequestStack(),
            new TestSessionStorageFactory(),
        );

        $session = $factory->createSession();

        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);
        static::assertInstanceOf(StatefulFlashBag::class, $session->getFlashBag());
        static::assertSame($factory->getFlashBag(), $session->getFlashBag());
    }
}
