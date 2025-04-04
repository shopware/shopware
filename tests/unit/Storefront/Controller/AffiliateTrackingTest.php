<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Storefront\Controller\CheckoutController;
use Shopware\Storefront\Controller\RegisterController;
use Shopware\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
class AffiliateTrackingTest extends TestCase
{
    public function testRegisterControllerPrefersAffiliateCodeFromCookieOverSession(): void
    {
        $container = new Container();
        $controller = $this->getMockBuilder(RegisterController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContainer'])
            ->getMock();
        
        $controller->method('getContainer')->willReturn($container);
        
        $session = new Session(new MockArraySessionStorage());
        $session->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, 'session-affiliate-code');
        $session->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, 'session-campaign-code');
        
        $request = new Request();
        $request->cookies->set('affiliate-code', 'cookie-affiliate-code');
        $request->cookies->set('campaign-code', 'cookie-campaign-code');
        
        $dataBag = new RequestDataBag();
        
        $method = new \ReflectionMethod(RegisterController::class, 'prepareAffiliateTracking');
        $method->setAccessible(true);
        
        $result = $method->invoke($controller, $dataBag, $session, $request);
        
        static::assertSame('cookie-affiliate-code', $result->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY));
        static::assertSame('cookie-campaign-code', $result->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY));
    }
    
    public function testRegisterControllerFallsBackToSessionWhenCookiesNotPresent(): void
    {
        $container = new Container();
        $controller = $this->getMockBuilder(RegisterController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContainer'])
            ->getMock();
        
        $controller->method('getContainer')->willReturn($container);
        
        $session = new Session(new MockArraySessionStorage());
        $session->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, 'session-affiliate-code');
        $session->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, 'session-campaign-code');
        
        $request = new Request();
        
        $dataBag = new RequestDataBag();
        
        $method = new \ReflectionMethod(RegisterController::class, 'prepareAffiliateTracking');
        $method->setAccessible(true);
        
        $result = $method->invoke($controller, $dataBag, $session, $request);
        
        static::assertSame('session-affiliate-code', $result->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY));
        static::assertSame('session-campaign-code', $result->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY));
    }
    
    public function testCheckoutControllerPrefersAffiliateCodeFromCookieOverSession(): void
    {
        $container = new Container();
        $controller = $this->getMockBuilder(CheckoutController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContainer'])
            ->getMock();
        
        $controller->method('getContainer')->willReturn($container);
        
        $session = new Session(new MockArraySessionStorage());
        $session->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, 'session-affiliate-code');
        $session->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, 'session-campaign-code');
        
        $request = new Request();
        $request->cookies->set('affiliate-code', 'cookie-affiliate-code');
        $request->cookies->set('campaign-code', 'cookie-campaign-code');
        
        $dataBag = new RequestDataBag();
        
        $method = new \ReflectionMethod(CheckoutController::class, 'addAffiliateTracking');
        $method->setAccessible(true);
        
        $method->invoke($controller, $dataBag, $session, $request);
        
        static::assertSame('cookie-affiliate-code', $dataBag->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY));
        static::assertSame('cookie-campaign-code', $dataBag->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY));
    }
    
    public function testCheckoutControllerFallsBackToSessionWhenCookiesNotPresent(): void
    {
        $container = new Container();
        $controller = $this->getMockBuilder(CheckoutController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContainer'])
            ->getMock();
        
        $controller->method('getContainer')->willReturn($container);
        
        $session = new Session(new MockArraySessionStorage());
        $session->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, 'session-affiliate-code');
        $session->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, 'session-campaign-code');
        
        $request = new Request();
        
        $dataBag = new RequestDataBag();
        
        $method = new \ReflectionMethod(CheckoutController::class, 'addAffiliateTracking');
        $method->setAccessible(true);
        
        $method->invoke($controller, $dataBag, $session, $request);
        
        static::assertSame('session-affiliate-code', $dataBag->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY));
        static::assertSame('session-campaign-code', $dataBag->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY));
    }
}
