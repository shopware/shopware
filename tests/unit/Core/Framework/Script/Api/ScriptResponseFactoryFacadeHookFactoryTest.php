<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
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

    private function buildFactory(): ScriptResponseFactoryFacadeHookFactory
    {
        return new ScriptResponseFactoryFacadeHookFactory(
            static::createStub(RouterInterface::class),
        );
    }
}
