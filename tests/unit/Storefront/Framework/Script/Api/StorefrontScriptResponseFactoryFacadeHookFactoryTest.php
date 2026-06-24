<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\ScriptController;
use Shopware\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacade;
use Shopware\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacadeHookFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(StorefrontScriptResponseFactoryFacadeHookFactory::class)]
class StorefrontScriptResponseFactoryFacadeHookFactoryTest extends TestCase
{
    #[TestDox('getName returns the documented "response" script-service identifier')]
    public function testGetNameIsResponse(): void
    {
        static::assertSame('response', $this->buildFactory()->getName());
    }

    #[TestDox('factory() builds the Storefront facade and forwards the hook SalesChannelContext into render()')]
    public function testFactoryBuildsStorefrontFacadeWithSalesChannelContext(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $hook = $this->createSalesChannelContextAwareHook($salesChannelContext);

        $scriptController = $this->createMock(ScriptController::class);
        $scriptController->expects($this->once())
            ->method('renderStorefrontForScript')
            ->with('@Storefront/foo.html.twig', [])
            ->willReturn(new Response('ok'));

        $facade = $this->buildFactory($scriptController)->factory($hook, static::createStub(Script::class));

        static::assertInstanceOf(StorefrontScriptResponseFactoryFacade::class, $facade);
        // render() succeeding proves the SalesChannelContext was forwarded (otherwise it throws).
        $facade->render('@Storefront/foo.html.twig');
    }

    private function buildFactory(?ScriptController $scriptController = null): StorefrontScriptResponseFactoryFacadeHookFactory
    {
        return new StorefrontScriptResponseFactoryFacadeHookFactory(
            static::createStub(RouterInterface::class),
            $scriptController ?? static::createStub(ScriptController::class),
        );
    }

    private function createSalesChannelContextAwareHook(SalesChannelContext $salesChannelContext): Hook&SalesChannelContextAware
    {
        return new class(Context::createDefaultContext(), $salesChannelContext) extends Hook implements SalesChannelContextAware {
            public function __construct(
                Context $context,
                private readonly SalesChannelContext $salesChannelContext,
            ) {
                parent::__construct($context);
            }

            public function getName(): string
            {
                return 'test.hook';
            }

            public static function getServiceIds(): array
            {
                return [];
            }

            public function getSalesChannelContext(): SalesChannelContext
            {
                return $this->salesChannelContext;
            }
        };
    }
}
