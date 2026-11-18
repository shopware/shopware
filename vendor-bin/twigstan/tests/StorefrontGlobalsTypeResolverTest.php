<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PhpParser\Node\Expr\AssignOp\Plus;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ObjectType;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bridge\Twig\AppVariable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use TwigStan\PHPStan\DynamicReturnType\GetGlobalsReturnType;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontGlobalsTypeResolver::class)]
class StorefrontGlobalsTypeResolverTest extends TypeInferenceTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/fixtures/storefront-globals.neon'];
    }

    public function testInfersGlobalsAndPreservesExplicitInputs(): void
    {
        $assertions = self::gatherAssertTypes(__DIR__ . '/fixtures/storefront-globals.php');
        static::assertNotEmpty($assertions);
        foreach ($assertions as $assertion) {
            $this->assertFileAsserts(...$assertion);
        }
    }

    public function testPreservesRealGlobalsAndKeepsRequestGlobalsOptional(): void
    {
        $twig = new Environment(new ArrayLoader());
        $twig->addGlobal('app', new AppVariable());
        $twig->addGlobal('pluginGlobal', new \stdClass());
        $twig->addGlobal('themeId', 'custom-theme');

        $node = new Plus(new Variable('context'), new MethodCall(new Variable('twig'), 'getGlobals'));
        $scope = static::createStubForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);
        $scope->method('getType')->willReturnCallback(static fn ($expr) => $expr instanceof Variable && $expr->name === 'context'
            ? new ConstantArrayType([], [])
            : new ObjectType(Environment::class));
        $method = self::getContainer()->getByType(ReflectionProvider::class)->getClass(Environment::class)->getNativeMethod('getGlobals');
        $scope->method('getMethodReflection')->willReturn($method);
        $resolver = new StorefrontGlobalsTypeResolver(new GetGlobalsReturnType($twig, appUserClass: null));

        $type = $resolver->getType($node, $scope);

        static::assertNotNull($type);
        static::assertTrue($type->hasOffsetValueType(new ConstantStringType('app'))->yes());
        static::assertTrue((new ObjectType(AppVariable::class))->equals($type->getOffsetValueType(new ConstantStringType('app'))));
        static::assertTrue((new ObjectType(\stdClass::class))->equals($type->getOffsetValueType(new ConstantStringType('pluginGlobal'))));
        static::assertTrue($type->hasOffsetValueType(new ConstantStringType('themeId'))->yes());
        static::assertTrue($type->getOffsetValueType(new ConstantStringType('themeId'))->isString()->yes());
        static::assertTrue($type->hasOffsetValueType(new ConstantStringType('context'))->maybe());
        static::assertTrue((new ObjectType(SalesChannelContext::class))->equals($type->getOffsetValueType(new ConstantStringType('context'))));
        static::assertTrue($type->hasOffsetValueType(new ConstantStringType('headerParameters'))->no());
    }

    public function testDoesNotChangeCallsOutsideTwigGlobalsInference(): void
    {
        $scope = static::createStubForIntersectionOfInterfaces([Scope::class, DependencyTracker::class]);
        static::assertNull((new StorefrontGlobalsTypeResolver())->getType(new Plus(new Variable('context'), new MethodCall(new Variable('twig'), 'getGlobals')), $scope));

        $globals = new GetGlobalsReturnType(new Environment(new ArrayLoader()), appUserClass: null);
        $resolver = new StorefrontGlobalsTypeResolver($globals);
        static::assertNull($resolver->getType(new Variable('context'), $scope));
        static::assertNull($resolver->getType(new MethodCall(new Variable('twig'), 'otherMethod'), $scope));
        static::assertNull($resolver->getType(new MethodCall(new Variable('twig'), 'getGlobals'), $scope));
        static::assertNull($resolver->getType(new Plus(new Variable('context'), new MethodCall(new Variable('twig'), 'otherMethod')), $scope));
        static::assertNull($resolver->getType(new Plus(new Variable('values'), new MethodCall(new Variable('twig'), 'getGlobals')), $scope));

        $scope->method('getType')->willReturn(new ObjectType(\stdClass::class));
        static::assertNull($resolver->getType(new Plus(new Variable('context'), new MethodCall(new Variable('service'), 'getGlobals')), $scope));
    }
}
