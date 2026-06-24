<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Controller\ScriptController;
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
        static::assertSame('response', $this->buildFactory()->getName());
    }

    #[TestDox('factory() builds a usable core response facade for a hook without a SalesChannelContext')]
    public function testFactoryBuildsCoreResponseFacade(): void
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

        $facade = $this->buildFactory()->factory($hook, static::createStub(Script::class));

        $response = $facade->json(['ok' => true], Response::HTTP_CREATED);
        static::assertSame(['ok' => true], $response->getBody()->all());
        static::assertSame(Response::HTTP_CREATED, $response->getCode());
    }

    #[TestDox('factory() keeps the deprecated render BC path when Storefront and SalesChannelContext are available')]
    #[IgnoreDeprecations]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFactoryBuildsCoreResponseFacadeWithLegacyRenderSupport(): void
    {
        $hook = $this->createSalesChannelContextAwareHook(static::createStub(SalesChannelContext::class));

        $scriptController = $this->createMock(ScriptController::class);
        $scriptController->expects($this->once())
            ->method('renderStorefrontForScript')
            ->with('@Storefront/foo.html.twig', [])
            ->willReturn(new Response('ok'));

        $facade = $this->buildFactory($scriptController)->factory($hook, static::createStub(Script::class));

        $response = $facade->render('@Storefront/foo.html.twig');

        static::assertSame('ok', $response->getInner()?->getContent());
    }

    private function buildFactory(?ScriptController $scriptController = null): ScriptResponseFactoryFacadeHookFactory
    {
        return new ScriptResponseFactoryFacadeHookFactory(
            static::createStub(RouterInterface::class),
            $scriptController,
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
