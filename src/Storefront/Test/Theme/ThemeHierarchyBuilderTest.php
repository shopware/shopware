<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Theme\Twig\ThemeInheritanceBuilderInterface;
use Shopware\Storefront\Theme\Twig\ThemeNamespaceHierarchyBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ThemeHierarchyBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ThemeNamespaceHierarchyBuilder
     */
    private $builder;

    public function setUp(): void
    {
        $this->builder = new ThemeNamespaceHierarchyBuilder(new TestInheritanceBuilder());
    }

    public function testThemesAreEmptyIfRequestHasNoValidAttributes(): void
    {
        $request = Request::createFromGlobals();

        $this->builder->requestEvent(new RequestEvent($this->getKernel(), $request, HttpKernelInterface::MASTER_REQUEST));

        $this->assertThemes([], $this->builder);
    }

    public function testThemesIfThemeNameIsSet(): void
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent($this->getKernel(), $request, HttpKernelInterface::MASTER_REQUEST));

        $this->assertThemes([
            'Storefront' => true,
            'TestTheme' => true,
        ], $this->builder);
    }

    public function testThemesIfBaseNameIsSet(): void
    {
        $request = Request::createFromGlobals();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, null);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent($this->getKernel(), $request, HttpKernelInterface::MASTER_REQUEST));

        $this->assertThemes([
            'Storefront' => true,
            'TestTheme' => true,
        ], $this->builder);
    }

    public function testItReturnsItsInputIfNoThemesAreSet(): void
    {
        $bundles = ['a', 'b'];

        $hierarchy = $this->builder->buildNamespaceHierarchy(['a', 'b']);

        static::assertEquals($bundles, $hierarchy);
    }

    public function testItPassesBundlesAndThemesToBuilder(): void
    {
        $bundles = ['a', 'b'];

        $request = Request::createFromGlobals();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'TestTheme');

        $this->builder->requestEvent(new RequestEvent($this->getKernel(), $request, HttpKernelInterface::MASTER_REQUEST));

        $hierarchy = $this->builder->buildNamespaceHierarchy($bundles);

        static::assertEquals([
            'Storefront' => true,
            'TestTheme' => true,
        ], $hierarchy);
    }

    private function assertThemes(array $expectation, ThemeNamespaceHierarchyBuilder $builder): void
    {
        $refObj = new \ReflectionObject($builder);
        $refProperty = $refObj->getProperty('themes');
        $refProperty->setAccessible(true);

        static::assertEquals($expectation, $refProperty->getValue($builder));
    }
}

class TestInheritanceBuilder implements ThemeInheritanceBuilderInterface
{
    public function build(array $bundles, array $themes): array
    {
        return $themes;
    }
}
