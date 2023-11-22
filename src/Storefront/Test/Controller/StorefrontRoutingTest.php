<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Routing\Exception\InvalidRouteScopeException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class StorefrontRoutingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testForwardFromAddPromotionToHomePage(): void
    {
        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            StorefrontRenderEvent::class,
            function (StorefrontRenderEvent $event): void {
                $data = $event->getParameters();

                static::assertInstanceOf(NavigationPage::class, $data['page']);
                static::assertInstanceOf(CmsPageEntity::class, $data['page']->getCmsPage());
                static::assertSame('Default listing layout', $data['page']->getCmsPage()->getName());
            }
        );

        $response = $this->request(
            'POST',
            '/checkout/promotion/add',
            $this->tokenize('frontend.checkout.promotion.add', [
                'forwardTo' => 'frontend.home.page',
            ])
        );

        static::assertSame(200, $response->getStatusCode());
    }

    public function testForwardFromAddPromotionToApiFails(): void
    {
        $response = $this->request(
            'POST',
            '/checkout/promotion/add',
            $this->tokenize('frontend.checkout.promotion.add', [
                'forwardTo' => 'api.action.user.user-recovery.hash',
            ])
        );

        static::assertInstanceOf(Response::class, $response);
        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
        static::assertStringContainsString(InvalidRouteScopeException::class, $response->getContent());
    }
}
