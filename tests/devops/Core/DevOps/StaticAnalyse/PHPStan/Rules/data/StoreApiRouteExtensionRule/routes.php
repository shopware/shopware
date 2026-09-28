<?php declare(strict_types=1);

namespace StoreApiRouteExtensionRuleFixtures;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @extends Extension<string>
 */
final class TestExtension extends Extension
{
    public const NAME = 'test.route';
}

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class Routes
{
    public function __construct(private readonly ExtensionDispatcher $extensions)
    {
    }

    #[Route('/valid')]
    public function valid(): string
    {
        return $this->extensions->publish(
            name: TestExtension::NAME,
            extension: new TestExtension(),
            function: $this->body(...),
        );
    }

    #[Route('/positional')]
    public function positional(): string
    {
        return $this->extensions->publish(TestExtension::NAME, new TestExtension(), $this->body(...));
    }

    #[Route('/missing')]
    public function missing(): string
    {
        return 'missing';
    }

    #[Route('/partial')]
    public function partial(): string
    {
        $this->extensions->publish(TestExtension::NAME, new TestExtension(), $this->body(...));

        return 'outside dispatcher';
    }

    #[Route('/public-body')]
    public function publicBody(): string
    {
        return $this->extensions->publish(TestExtension::NAME, new TestExtension(), $this->helper(...));
    }

    #[Route('/closure')]
    public function closure(): string
    {
        return $this->extensions->publish(TestExtension::NAME, new TestExtension(), fn () => 'inline');
    }

    #[Route('/wrong-extension')]
    public function wrongExtension(): string
    {
        return $this->extensions->publish(TestExtension::NAME, new \stdClass(), $this->body(...));
    }

    #[Route('/admin', defaults: ['_routeScope' => ['api']])]
    public function admin(): string
    {
        return 'not a store-api route';
    }

    public function helper(): string
    {
        return 'not an endpoint';
    }

    private function body(): string
    {
        return 'result';
    }
}

class MethodScopedRoute
{
    #[Route('/method-scope', defaults: ['_routeScope' => ['store-api']])]
    public function load(): string
    {
        return 'missing';
    }
}

#[Route(defaults: ['_routeScope' => ['api']])]
class AdminRoute
{
    #[Route('/admin')]
    public function load(): string
    {
        return 'admin';
    }
}

#[Route(defaults: ['_routeScope' => ['store-api']])]
class LegacyRoute
{
    #[Route('/legacy')]
    public function load(): string
    {
        return 'existing';
    }

    #[Route('/new')]
    public function newEndpoint(): string
    {
        return 'new methods are not exempt';
    }
}

abstract class AbstractRoute
{
    abstract public function getDecorated(): self;
}

#[Route(defaults: ['_routeScope' => ['store-api']])]
class DecoratedRoute extends AbstractRoute
{
    public function getDecorated(): AbstractRoute
    {
        return $this;
    }

    #[Route('/decorated')]
    public function load(): string
    {
        return 'decorator';
    }
}

class FakeDispatcher
{
    public function publish(string $name, Extension $extension, callable $function): string
    {
        return $function();
    }
}

#[Route(defaults: ['_routeScope' => ['store-api']])]
class WrongDispatcherRoute
{
    public function __construct(private readonly FakeDispatcher $extensions)
    {
    }

    #[Route('/fake-dispatcher')]
    public function load(): string
    {
        return $this->extensions->publish(TestExtension::NAME, new TestExtension(), $this->body(...));
    }

    private function body(): string
    {
        return 'wrong dispatcher';
    }
}

class RepeatedRouteAttributes
{
    #[Route('/admin', defaults: ['_routeScope' => ['api']])]
    #[Route('/store', defaults: ['_routeScope' => ['store-api']])]
    public function load(): string
    {
        return 'store-api scope must not be hidden by the first attribute';
    }
}

abstract class AbstractLoadRoute
{
    abstract public function load(): string;
}

#[Route(defaults: ['_routeScope' => ['store-api']])]
class InheritedContractRoute extends AbstractLoadRoute
{
    #[Route('/inherited-contract')]
    public function load(): string
    {
        return 'abstract route without getDecorated';
    }
}

class MethodScopedValidRoute
{
    public function __construct(private readonly ExtensionDispatcher $extensions)
    {
    }

    #[Route('/valid-method-scope', defaults: ['_routeScope' => ['store-api']])]
    public function load(): string
    {
        return $this->extensions->publish(
            function: $this->resolve(...),
            extension: new TestExtension(),
            name: TestExtension::NAME,
        );
    }

    private function resolve(): string
    {
        return 'named arguments may be reordered';
    }
}
