<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\ScriptController;
use Shopware\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacade;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontScriptResponseFactoryFacade::class)]
class StorefrontScriptResponseFactoryFacadeTest extends TestCase
{
    #[TestDox('render() delegates to ScriptController and wraps the rendered response')]
    public function testRenderDelegatesToScriptController(): void
    {
        $rendered = new Response('rendered storefront html', Response::HTTP_ACCEPTED);

        $scriptController = $this->createMock(ScriptController::class);
        $scriptController->expects($this->once())
            ->method('renderStorefrontForScript')
            ->with('@Storefront/detail.html.twig', ['page' => 'data'])
            ->willReturn($rendered);

        $facade = $this->buildFacade(
            scriptController: $scriptController,
            salesChannelContext: static::createStub(SalesChannelContext::class),
        );

        $response = $facade->render('@Storefront/detail.html.twig', ['page' => 'data']);

        static::assertSame($rendered, $response->getInner());
        static::assertSame(Response::HTTP_ACCEPTED, $response->getCode());
    }

    private function buildFacade(
        ?RouterInterface $router = null,
        ?ScriptController $scriptController = null,
        ?SalesChannelContext $salesChannelContext = null,
    ): StorefrontScriptResponseFactoryFacade {
        return new StorefrontScriptResponseFactoryFacade(
            $router ?? static::createStub(RouterInterface::class),
            $scriptController ?? static::createStub(ScriptController::class),
            $salesChannelContext ?? static::createStub(SalesChannelContext::class),
        );
    }
}
