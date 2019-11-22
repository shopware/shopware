<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Routing\Exception\InvalidRouteScopeException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Framework\Twig\ErrorTemplateStruct;
use Shopware\Storefront\Page\Navigation\NavigationPage;

class StorefrontRoutingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testForwardFromNewsletterToHomePage(): void
    {
        $response = $this->request(
            'POST',
            'newsletter',
            $this->tokenize('frontend.newsletter.register.handle', [
                'forwardTo' => 'frontend.home.page',
            ])
        );

        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertInstanceOf(NavigationPage::class, $response->getData()['page']);
        static::assertInstanceOf(CmsPageEntity::class, $response->getData()['page']->getCmsPage());
        static::assertSame('Default category layout', $response->getData()['page']->getCmsPage()->getName());
        static::assertSame(200, $response->getStatusCode());
    }

    public function testForwardFromNewsletterToApiFails(): void
    {
        $response = $this->request(
            'POST',
            'newsletter',
            $this->tokenize('frontend.newsletter.register.handle', [
                'forwardTo' => 'sales-channel-api.category.detail',
                'forwardParameters' => json_encode(['version' => 1, 'id' => Uuid::randomHex()]),
            ])
        );

        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertInstanceOf(ErrorTemplateStruct::class, $response->getData()['page']);
        static::assertInstanceOf(InvalidRouteScopeException::class, $response->getData()['page']->getArguments()['exception']);
    }
}
