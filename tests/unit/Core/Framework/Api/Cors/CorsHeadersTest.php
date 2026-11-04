<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Cors;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Cors\CorsHeaders;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CorsHeaders::class)]
class CorsHeadersTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $headers = new CorsHeaders();

        static::assertSame([], $headers->getAllowed());
        static::assertSame([], $headers->getExposed());
    }

    public function testTheTwoListsAreIndependent(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('sw-plan');
        $headers->addExposed('sw-state');

        static::assertSame(['sw-plan'], $headers->getAllowed());
        static::assertSame(['sw-state'], $headers->getExposed());
    }

    public function testHeadersKeepTheOrderTheyWereAddedIn(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('sw-first', 'sw-second');
        $headers->addAllowed('sw-third');

        static::assertSame(['sw-first', 'sw-second', 'sw-third'], $headers->getAllowed());
    }

    public function testDuplicatesKeepTheFirstSpelling(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('SW-Plan');
        $headers->addAllowed('sw-plan');
        $headers->addExposed('sw-state', 'SW-STATE');

        static::assertSame(['SW-Plan'], $headers->getAllowed());
        static::assertSame(['sw-state'], $headers->getExposed());
    }

    public function testRemovingMatchesAnySpelling(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('sw-plan', 'sw-interval');
        $headers->addExposed('sw-state');

        $headers->removeAllowed('SW-PLAN');
        $headers->removeExposed('Sw-State');

        static::assertSame(['sw-interval'], $headers->getAllowed());
        static::assertSame([], $headers->getExposed());
    }

    public function testRemovingAHeaderThatWasNeverAddedIsANoop(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('sw-plan');

        $headers->removeAllowed('sw-unknown');

        static::assertSame(['sw-plan'], $headers->getAllowed());
    }

    public function testRemovingFromOneListLeavesTheOtherAlone(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('sw-plan');
        $headers->addExposed('sw-plan');

        $headers->removeAllowed('sw-plan');

        static::assertSame([], $headers->getAllowed());
        static::assertSame(['sw-plan'], $headers->getExposed());
    }

    public function testAHeaderCanBeAddedAgainAfterItWasRemoved(): void
    {
        $headers = new CorsHeaders();
        $headers->addAllowed('sw-plan');
        $headers->removeAllowed('sw-plan');
        $headers->addAllowed('SW-Plan');

        static::assertSame(['SW-Plan'], $headers->getAllowed());
    }
}
