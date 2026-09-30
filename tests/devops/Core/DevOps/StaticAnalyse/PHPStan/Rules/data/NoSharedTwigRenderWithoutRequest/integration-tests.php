<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\SharedTwigFixture;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * phpstan-symfony types `get('twig')` from the compiled container in the real run; the fixture spells it out.
 */
interface TwigContainer extends ContainerInterface
{
    public function get(string $id): Environment;
}

abstract class FixtureTestCase extends TestCase
{
    protected static function container(): TwigContainer
    {
        throw new \LogicException('Analysed by PHPStan only, never run.');
    }
}

class RendersThroughTheContainerDirectlyTest extends FixtureTestCase
{
    public function testRender(): void
    {
        static::container()->get('twig')->render('a.html.twig');
    }
}

class RendersThroughALocalVariableTest extends FixtureTestCase
{
    public function testRender(): void
    {
        $twig = static::container()->get('twig');
        $twig->render('a.html.twig');
    }
}

class RendersThroughAPropertySetUpInSetUpTest extends FixtureTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = static::container()->get('twig');
    }

    public function testRender(): void
    {
        $this->twig->createTemplate('{{ value }}')->render();
    }
}

class CompilesTemplatesWithoutRenderingTest extends FixtureTestCase
{
    public function testCompile(): void
    {
        // handing out a template resolves no globals, only rendering it does
        $twig = static::container()->get('twig');
        $twig->load('a.html.twig');
        $twig->createTemplate('{{ value }}');
    }
}

class RendersATemplateVariableLaterTest extends FixtureTestCase
{
    public function testRender(): void
    {
        $template = static::container()->get('twig')->createTemplate('{{ value }}');

        $template->render();
    }
}

class RendersABlockOfATemplateFromAPropertyTest extends FixtureTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = static::container()->get('twig');
    }

    public function testRender(): void
    {
        $template = $this->twig->load('a.html.twig');
        $template->renderBlock('content');
    }
}

class RendersThroughAnAliasedPropertyTest extends FixtureTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $twig = static::container()->get(Environment::class);
        $this->twig = $twig;
    }

    public function testRender(): void
    {
        $this->twig->display('a.html.twig');
    }
}

abstract class SharedTwigInParentTestCase extends FixtureTestCase
{
    protected Environment $twig;

    protected function setUp(): void
    {
        $this->twig = static::container()->get('twig');
    }
}

class RendersThroughAPropertyTheParentSetsUpTest extends SharedTwigInParentTestCase
{
    public function testRender(): void
    {
        $this->twig->render('a.html.twig');
    }
}

class PushesABareRequestTest extends FixtureTestCase
{
    private RequestStack $requestStack;

    public function testRender(): void
    {
        // a request without a sales channel context: the Storefront globals still resolve empty
        $this->requestStack->push(new Request());

        static::container()->get('twig')->render('a.html.twig');
    }
}

class RendersThroughItsOwnEnvironmentTest extends FixtureTestCase
{
    public function testRender(): void
    {
        $twig = new Environment(new ArrayLoader(['a.html.twig' => '{{ value }}']));
        $twig->render('a.html.twig');
    }
}

class RendersThroughAnotherContainerEnvironmentTest extends FixtureTestCase
{
    public function testRender(): void
    {
        static::container()->get('shopware.seo_url.twig')->render('a.html.twig');

        $seoTwig = static::container()->get('shopware.seo_url.twig');
        $seoTwig->render('a.html.twig');
    }
}

class PushesAStorefrontRequestTest extends FixtureTestCase
{
    private RequestStack $requestStack;

    public function testRender(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, new \stdClass());
        $this->requestStack->push($request);

        static::container()->get('twig')->render('a.html.twig');
    }
}

class SplitsTheStorefrontRequestSetUpAcrossTestsTest extends FixtureTestCase
{
    private RequestStack $requestStack;

    public function testBuildsAContextRequestWithoutPushingIt(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, new \stdClass());
    }

    public function testRendersInsideABareRequest(): void
    {
        $this->requestStack->push(new Request());

        static::container()->get('twig')->render('a.html.twig');
    }
}

trait StorefrontRequestFixture
{
    private RequestStack $requestStack;

    private function pushStorefrontRequest(): void
    {
        $this->requestStack->push(new Request(attributes: [PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => new \stdClass()]));
    }
}

class PushesAStorefrontRequestThroughATraitTest extends FixtureTestCase
{
    use StorefrontRequestFixture;

    public function testRender(): void
    {
        $this->pushStorefrontRequest();

        static::container()->get('twig')->render('a.html.twig');
    }
}

class AssignsSharedAndOwnEnvironmentsToTheSamePropertyTest extends FixtureTestCase
{
    private Environment $twig;

    public function testShared(): void
    {
        $this->twig = static::container()->get('twig');
    }

    public function testOwn(): void
    {
        $this->twig = new Environment(new ArrayLoader());
        $this->twig->render('a.html.twig');
    }
}

/**
 * Not a test class: a helper rendering outside PHPUnit is not the rule's concern.
 */
class TemplateRenderingHelper
{
    public function render(TwigContainer $container): string
    {
        return $container->get('twig')->render('a.html.twig');
    }
}
