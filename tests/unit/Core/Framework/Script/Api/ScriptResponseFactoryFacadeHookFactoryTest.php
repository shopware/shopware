<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Script\Execution\Script;
use Shopware\Core\Framework\Script\ScriptException;
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

    #[TestDox('factory() builds the core response facade whose render() requires the Storefront bundle')]
    #[IgnoreDeprecations]
    public function testFactoryBuildsCoreFacadeWithoutRenderSupport(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

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

        $this->expectException(ScriptException::class);
        $this->expectExceptionMessageMatches('/storefront.*bundle/i');

        $facade->render('@Storefront/foo.html.twig');
    }

    private function buildFactory(): ScriptResponseFactoryFacadeHookFactory
    {
        return new ScriptResponseFactoryFacadeHookFactory(
            static::createStub(RouterInterface::class),
        );
    }
}
