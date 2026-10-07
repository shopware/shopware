<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\InvalidRouteScopeException;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Test\Integration\Traits\EventHookBehaviour;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class StorefrontRoutingTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use EventHookBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;
    use SessionTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testForwardFromAddPromotionToHomePage(): void
    {
        $renderedParameters = [];
        $this->onEvent(
            StorefrontRenderEvent::class,
            static function (StorefrontRenderEvent $event) use (&$renderedParameters): void {
                $skippedViews = [
                    '@Storefront/storefront/layout/header.html.twig',
                    '@Storefront/storefront/layout/footer.html.twig',
                ];
                if (\in_array($event->getView(), $skippedViews, true)) {
                    return;
                }

                $renderedParameters[] = $event->getParameters();
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
        static::assertCount(1, $renderedParameters);
        $page = $renderedParameters[0]['page'];
        static::assertInstanceOf(NavigationPage::class, $page);
        static::assertInstanceOf(CmsPageEntity::class, $page->getCmsPage());
        static::assertSame('Default listing layout', $page->getCmsPage()->getName());
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

        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertStringContainsString(InvalidRouteScopeException::class, $response->getContent());
    }
}
