<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReflectionOnNonPublicMethodsRule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\Fixture\ReflectionTarget;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class Cases extends TestCase
{
    public function testReflectionMethodOnPrivate(): void
    {
        // FLAGGED: private method of a Shopware class
        $method = new \ReflectionMethod(ReflectionTarget::class, 'hiddenCalculation');

        $method->invoke(new ReflectionTarget());
    }

    public function testReflectionMethodOnProtected(): void
    {
        // FLAGGED: protected method of a Shopware class
        $method = new \ReflectionMethod(ReflectionTarget::class, 'guardedStep');

        $method->invoke(new ReflectionTarget());
    }

    public function testReflectionMethodSingleArgumentForm(): void
    {
        // FLAGGED: single-argument form resolves to the same private method
        $method = new \ReflectionMethod('Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\Fixture\ReflectionTarget::hiddenCalculation');

        $method->invoke(new ReflectionTarget());
    }

    public function testReflectionMethodOnObjectInstance(): void
    {
        $target = new ReflectionTarget();

        // FLAGGED: the object instance types as the Shopware class
        $method = new \ReflectionMethod($target, 'hiddenCalculation');

        $method->invoke($target);
    }

    public function testReflectionClassGetMethod(): void
    {
        // FLAGGED: getMethod on ReflectionClass<ReflectionTarget>
        $method = (new \ReflectionClass(ReflectionTarget::class))->getMethod('hiddenCalculation');

        $method->invoke(new ReflectionTarget());
    }

    public function testReflectionClassVariableGetMethod(): void
    {
        $reflection = new \ReflectionClass(ReflectionTarget::class);

        // FLAGGED: the variable keeps the template type
        $method = $reflection->getMethod('guardedStep');

        $method->invoke(new ReflectionTarget());
    }

    public function testSetAccessible(): void
    {
        $method = (new \ReflectionClass(Request::class))->getMethod('preparePathInfo');

        // FLAGGED: setAccessible is a no-op since PHP 8.1, reported for any target
        $method->setAccessible(true);
    }

    public function testPublicMethodPasses(): void
    {
        // NOT flagged: public method
        $method = new \ReflectionMethod(ReflectionTarget::class, 'publicApi');

        static::assertSame('publicApi', $method->getName());
    }

    public function testMetadataReadOnPublicPasses(): void
    {
        // NOT flagged: reading metadata off ReflectionClass
        $reflection = new \ReflectionClass(ReflectionTarget::class);

        static::assertTrue($reflection->getMethod('publicApi')->isPublic());
    }

    public function testThirdPartyTargetPasses(): void
    {
        // NOT flagged: reflection into a vendor class stays acceptable
        $method = new \ReflectionMethod(Request::class, 'preparePathInfo');

        $method->invoke(new Request());
    }

    public function testTestSupportTargetPasses(): void
    {
        // NOT flagged: test-support classes are not production API
        $method = new \ReflectionMethod(TestSupportTarget::class, 'helperInternal');

        $method->invoke(new TestSupportTarget());
    }

    public function testUnknownMethodPasses(): void
    {
        // NOT flagged: the method does not exist on the target, nothing to prove
        $method = new \ReflectionMethod(ReflectionTarget::class, 'doesNotExist');

        static::assertNotNull($method);
    }

    public function testReflectionPropertyPasses(): void
    {
        // NOT flagged: property reflection is out of scope, even with setAccessible
        $property = new \ReflectionProperty(ReflectionTarget::class, 'anything');
        $property->setAccessible(true);
    }
}
