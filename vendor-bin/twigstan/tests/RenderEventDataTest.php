<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\StorefrontRouteEventSubscriber;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @phpstan-import-type Listener from RenderEventListeners
 * @phpstan-import-type Write from RenderEventParameterCollector
 *
 * @internal
 */
#[Package('framework')]
class RenderEventDataTest extends PHPStanTestCase
{
    private TypeStringResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = self::getContainer()->getByType(TypeStringResolver::class);
    }

    public function testListenerOverridesControllerDataAndConditionalWritesKeepAlternatives(): void
    {
        $data = new RenderEventData($this->resolver, [$this->listener('one')], [
            $this->write('one', 'replaced', 'int'),
            $this->write('one', 'conditional', 'int', optional: true),
            $this->write('one', 'added', '\\stdClass', optional: true),
        ]);
        $result = $data->apply($this->resolver->resolve('array{replaced: string, conditional: string}'), 'example.twig');

        static::assertTrue($result->getOffsetValueType(new ConstantStringType('replaced'))->isInteger()->yes());
        $conditional = $result->getOffsetValueType(new ConstantStringType('conditional'));
        static::assertTrue($conditional->isSuperTypeOf($this->resolver->resolve('int|string'))->yes());
        static::assertTrue($result->hasOffsetValueType(new ConstantStringType('conditional'))->yes());
        static::assertTrue($result->hasOffsetValueType(new ConstantStringType('added'))->maybe());
    }

    public function testTemplateRestrictedWritesAreNotAddedToOtherTemplates(): void
    {
        $data = new RenderEventData($this->resolver, [$this->listener('one')], [$this->write('one', 'target', 'int', views: ['product.twig'])]);
        $context = new ConstantArrayType([], []);

        static::assertTrue($data->apply($context, 'product.twig')->hasOffsetValueType(new ConstantStringType('target'))->yes());
        static::assertTrue($data->apply($context, 'account.twig')->hasOffsetValueType(new ConstantStringType('target'))->no());
    }

    public function testRouteAndScopeDispatchesRunInsideTheBridgeBeforeLowerPriorityListeners(): void
    {
        $data = new RenderEventData($this->resolver, [
            $this->listener('after', priority: -20),
            $this->listener('route', event: 'frontend.product.render', priority: 5000),
            $this->listener('scope', event: 'storefront.scope.render'),
            $this->listener('unrelated', event: 'frontend.account.render'),
            $this->listener(StorefrontRouteEventSubscriber::class . '::render', priority: -10),
            $this->listener('before', priority: 20),
        ], [
            $this->write('before', 'value', 'string'),
            $this->write('route', 'value', 'int'),
            $this->write('scope', 'scoped', 'true'),
            $this->write('unrelated', 'unrelated', 'true'),
            $this->write('after', 'value', 'bool'),
        ]);
        $result = $data->apply(new ConstantArrayType([], []), 'example.twig', ['frontend.product'], ['storefront']);

        static::assertTrue($result->getOffsetValueType(new ConstantStringType('value'))->isBoolean()->yes());
        static::assertTrue($result->hasOffsetValueType(new ConstantStringType('scoped'))->yes());
        static::assertTrue($result->hasOffsetValueType(new ConstantStringType('unrelated'))->no());
    }

    public function testEqualPrioritiesKeepRegistrationOrder(): void
    {
        $data = new RenderEventData($this->resolver, [
            $this->listener('second', order: 2),
            $this->listener('first', order: 1),
        ], [$this->write('first', 'value', 'int'), $this->write('second', 'value', 'string')]);

        static::assertTrue($data->apply(new ConstantArrayType([], []), 'example.twig')->getOffsetValueType(new ConstantStringType('value'))->isString()->yes());
    }

    public function testReadsControllerRouteAttributesAndTracksListenerSummaryChanges(): void
    {
        $data = new RenderEventData($this->resolver, [
            $this->listener(StorefrontRouteEventSubscriber::class . '::render', priority: -10),
            $this->listener('route', event: 'prefix.detail.render'),
            $this->listener('scope', event: 'storefront.scope.render'),
        ], [$this->write('route', 'routed', 'int'), $this->write('scope', 'scoped', 'bool')], '/listener-context.json');
        /** @var Scope&DependencyTracker&MockObject $scope */
        $scope = static::createMockForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);
        $scope->method('getClassReflection')->willReturn(self::getContainer()->getByType(ReflectionProvider::class)->getClass(RenderRouteFixture::class));
        $scope->method('getFunctionName')->willReturn('detail');
        $scope->expects($this->once())->method('trackFileDependency')->with('/listener-context.json');

        $result = $data->applyToRender(new ConstantArrayType([], []), 'example.twig', $scope);

        static::assertTrue($result->hasOffsetValueType(new ConstantStringType('routed'))->yes());
        static::assertTrue($result->hasOffsetValueType(new ConstantStringType('scoped'))->yes());
    }

    public function testControllerRoutesAreAlternativeRequests(): void
    {
        $data = new RenderEventData($this->resolver, [
            $this->listener(StorefrontRouteEventSubscriber::class . '::render', priority: -10),
            $this->listener('one', event: 'frontend.one.render'),
            $this->listener('two', event: 'frontend.two.render'),
        ], [$this->write('one', 'value', 'int'), $this->write('two', 'value', 'string')]);
        $value = $data->apply(new ConstantArrayType([], []), 'example.twig', ['frontend.one', 'frontend.two'])->getOffsetValueType(new ConstantStringType('value'));

        static::assertTrue($value->isSuperTypeOf($this->resolver->resolve('int|string'))->yes());
    }

    public function testStopPropagationPreventsLaterWrites(): void
    {
        $stop = [...$this->write('first', '', ''), 'kind' => 'stop'];
        $data = new RenderEventData($this->resolver, [$this->listener('first', order: 1), $this->listener('second', order: 2)], [
            $this->write('first', 'value', 'int'),
            $stop,
            $this->write('first', 'afterStop', 'true'),
            $this->write('second', 'value', 'string'),
        ]);

        $result = $data->apply(new ConstantArrayType([], []), 'example.twig');
        static::assertTrue($result->getOffsetValueType(new ConstantStringType('value'))->isInteger()->yes());
        static::assertTrue($result->hasOffsetValueType(new ConstantStringType('afterStop'))->yes());
    }

    public function testConditionalStopMakesLaterWritesConditional(): void
    {
        $data = new RenderEventData($this->resolver, [$this->listener('first', order: 1), $this->listener('second', order: 2)], [
            [...$this->write('first', '', '', optional: true), 'kind' => 'stop'],
            $this->write('second', 'value', 'string'),
        ]);

        static::assertTrue($data->apply(new ConstantArrayType([], []), 'example.twig')->hasOffsetValueType(new ConstantStringType('value'))->maybe());
    }

    /**
     * @return Listener
     */
    private function listener(string $callback, string $event = StorefrontRenderEvent::class, int $priority = 0, int $order = 0): array
    {
        return ['event' => $event, 'callback' => $callback, 'parameter' => 'event', 'file' => __FILE__, 'priority' => $priority, 'order' => $order];
    }

    /**
     * @param list<string> $views
     *
     * @return Write
     */
    private function write(string $callback, string $key, string $type, bool $optional = false, array $views = []): array
    {
        return ['callback' => $callback, 'key' => $key, 'type' => $type, 'optional' => $optional, 'views' => $views, 'file' => __FILE__, 'line' => 1, 'reason' => '', 'kind' => 'parameter'];
    }
}

/**
 * @internal
 */
#[Package('framework')]
#[Route(name: 'prefix.', defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['storefront']])]
class RenderRouteFixture
{
    #[Route(name: 'detail')]
    public function detail(): void
    {
    }
}
