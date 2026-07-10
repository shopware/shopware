<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Cookie\AgeVerificationCookieCollectListener;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AgeVerificationCookieCollectListener::class)]
class AgeVerificationCookieCollectListenerTest extends TestCase
{
    private AgeVerificationCookieCollectListener $listener;

    protected function setUp(): void
    {
        $this->listener = new AgeVerificationCookieCollectListener();
    }

    public function testCookieIsAddedToRequiredGroupAsHiddenEntry(): void
    {
        $requiredGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$requiredGroup]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $entry = $event->cookieGroupCollection
            ->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED)
            ?->getEntries()
            ?->get(AgeVerificationCookieCollectListener::COOKIE_NAME);

        static::assertNotNull($entry);
        static::assertTrue($entry->hidden);
        static::assertSame('1', $entry->value);
        static::assertSame('cookie.groupRequiredAgeVerification', $entry->name);
    }

    public function testNothingHappensWhenRequiredGroupIsMissing(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([new CookieGroup('some-other-group')]),
            new Request(),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        static::assertCount(1, $event->cookieGroupCollection);
        static::assertNull(
            $event->cookieGroupCollection->get('some-other-group')?->getEntries()
        );
    }
}
