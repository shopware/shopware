<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Captcha;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Framework\Captcha\CaptchaCookieCollectListener;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;

/**
 * @internal
 */
#[CoversClass(CaptchaCookieCollectListener::class)]
class CaptchaCookieCollectListenerTest extends TestCase
{
    private const CONFIG_KEY = 'core.basicInformation.activeCaptchasV2.' . GoogleReCaptchaV2::CAPTCHA_NAME . '.isActive';

    private StaticSystemConfigService $systemConfigService;

    private CaptchaCookieCollectListener $listener;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService([self::CONFIG_KEY => true]);
        $this->listener = new CaptchaCookieCollectListener($this->systemConfigService);
    }

    public function testCaptchaConfigNotActive(): void
    {
        $this->systemConfigService->set(self::CONFIG_KEY, false);

        /** @phpstan-ignore shopware.mockingSimpleObjects (A mock is used here to ensure that the method is not called) */
        $cookieCollection = $this->createMock(CookieGroupCollection::class);
        $cookieCollection->expects($this->never())->method('get');
        $event = new CookieGroupCollectEvent($cookieCollection, Generator::generateSalesChannelContext());

        $this->listener->__invoke($event);
    }

    public function testRequiredCookieGroupNotPresent(): void
    {
        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([new CookieGroup('test')]),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        static::assertCount(1, $event->cookieGroupCollection);
    }

    public function testCaptchaCookieIsAdded(): void
    {
        $cookieGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        $cookieGroup->isRequired = true;

        $event = new CookieGroupCollectEvent(
            new CookieGroupCollection([$cookieGroup]),
            Generator::generateSalesChannelContext()
        );

        $this->listener->__invoke($event);

        $captchaCookie = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED)?->getEntries()?->get('_GRECAPTCHA');
        static::assertNotNull($captchaCookie);
    }
}
