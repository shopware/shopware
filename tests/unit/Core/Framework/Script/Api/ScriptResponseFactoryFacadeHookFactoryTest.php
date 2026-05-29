<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Event\RenderStorefrontForScriptEvent;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\ScriptException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(ScriptResponseFactoryFacadeHookFactory::class)]
class ScriptResponseFactoryFacadeHookFactoryTest extends TestCase
{
    #[TestDox('getName returns the documented "response" script-service identifier')]
    public function testGetNameIsResponse(): void
    {
        static::assertSame('response', $this->buildFactory(new EventDispatcher())->getName());
    }

    #[TestDox('factory() forwards the hook SalesChannelContext into the facade (observed via the rendered event)')]
    public function testFactoryForwardsSalesChannelContextFromAwareHook(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $hook = $this->createSalesChannelContextAwareHook($salesChannelContext);

        $dispatcher = new EventDispatcher();
        $captured = null;
        $dispatcher->addListener(
            RenderStorefrontForScriptEvent::class,
            static function (RenderStorefrontForScriptEvent $event) use (&$captured): void {
                $captured = $event;
                $event->response = new Response('ok');
            }
        );

        $facade = $this->buildFactory($dispatcher)->factory($hook, static::createStub(Script::class));
        $facade->render('@Storefront/foo.html.twig');

        static::assertNotNull($captured);
        static::assertSame($salesChannelContext, $captured->salesChannelContext);
    }

    #[TestDox('factory() builds a context-less facade when the hook is not SalesChannelContextAware — render() then throws outside a sales-channel context')]
    public function testFactoryReturnsContextlessFacadeForNonAwareHook(): void
    {
        $hook = new class(Context::createDefaultContext()) extends Hook {
            public function getName(): string
            {
                return 'test.hook';
            }

            public static function getServiceIds(): array
            {
                return [];
            }
        };

        $facade = $this->buildFactory(new EventDispatcher())->factory($hook, static::createStub(Script::class));

        $this->expectException(ScriptException::class);
        $this->expectExceptionMessageMatches('/sales.?channel/i');

        $facade->render('@Storefront/foo.html.twig');
    }

    private function buildFactory(EventDispatcher $dispatcher): ScriptResponseFactoryFacadeHookFactory
    {
        return new ScriptResponseFactoryFacadeHookFactory(
            static::createStub(RouterInterface::class),
            $dispatcher,
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
