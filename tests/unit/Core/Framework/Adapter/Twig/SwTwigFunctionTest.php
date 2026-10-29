<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Runtime\EscaperRuntime;
use Twig\Source;
use Twig\Template;

/**
 * @internal
 */
#[CoversClass(SwTwigFunction::class)]
class SwTwigFunctionTest extends TestCase
{
    private MockObject&Environment $environmentMock;

    protected function setUp(): void
    {
        $this->environmentMock = $this->createMock(Environment::class);
        /** This is a fix for an autoload issue in the testsuite. Do not delete it. */
        class_exists(CoreExtension::class);
    }

    public function testSwGetAttributeValueNull(): void
    {
        $object = new ArrayStruct(['test' => null]);
        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertEquals('', $result);
    }

    public function testSwGetAttributeValueBool(): void
    {
        $object = new ArrayStruct(['test' => true]);
        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertTrue($result);

        $object = new ArrayStruct(['test' => false]);
        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertFalse($result);
    }

    public function testSwGetAttributeJustProperty(): void
    {
        $object = new ArrayStruct(['test' => 'value']);
        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'test');

        static::assertEquals('value', $result);
    }

    public function testSwGetAttributeGetterMethods(): void
    {
        $object = new StructForTests();
        $object->setNoGetter(99);
        $object->setValue('valueValue');
        $object->setVisible(true);

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'noGetter');

        static::assertNull($result);

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'value');

        static::assertEquals('valueValue', $result);

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'getValue');

        static::assertEquals('valueValue', $result);

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'visible');

        static::assertTrue($result);

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, 'isVisible');

        static::assertTrue($result);

        $result = SwTwigFunction::getAttribute(
            $this->environmentMock,
            new Source('', 'empty'),
            $object,
            'isVisible',
            [],
            Template::METHOD_CALL
        );

        static::assertTrue($result);
    }

    public function testEscapeFilterWithNullInput(): void
    {
        $env = $this->environmentMock;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));
        $result = SwTwigFunction::escapeFilter($env, null, 'html', 'UTF-8');

        static::assertEquals('', $result);
    }

    public function testEscapeFilterWithIntegerInput(): void
    {
        $env = $this->environmentMock;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));
        $result = SwTwigFunction::escapeFilter($env, 123, 'html', 'UTF-8');

        static::assertEquals('123', $result);
    }

    public function testEscapeFilterWithStringInput(): void
    {
        $env = $this->environmentMock;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));
        $result = SwTwigFunction::escapeFilter($env, 'test', 'html', 'UTF-8');

        static::assertEquals('test', $result);
    }

    public function testEscapeFilterReallyEscapeString(): void
    {
        $env = $this->environmentMock;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));
        $result = SwTwigFunction::escapeFilter($env, '<script>alert("test")</script>', 'html', 'UTF-8');

        static::assertEquals('&lt;script&gt;alert(&quot;test&quot;)&lt;/script&gt;', $result);
    }

    public function testEscapeFilterWithCachedStringInput(): void
    {
        $env = $this->environmentMock;
        $env->method('getRuntime')->willReturn(new EscaperRuntime($env));

        // First call to cache the result
        SwTwigFunction::escapeFilter($env, 'cached_string', 'html', 'UTF-8');

        // Second call to get the cached result
        $result = SwTwigFunction::escapeFilter($env, 'cached_string', 'html', 'UTF-8');

        static::assertEquals('cached_string', $result);
    }

    /**
     * @return \Generator<string, array{object: object, item: string, arguments: list<mixed>, type: string, method: string}>
     */
    public static function deniedContextCallProvider(): \Generator
    {
        yield 'setter on the context' => [
            'object' => Context::createDefaultContext(),
            'item' => 'setRuleIds',
            'arguments' => [['rule-id']],
            'type' => Template::METHOD_CALL,
            'method' => 'setRuleIds',
        ];

        yield 'scope switch with a callback' => [
            'object' => Context::createDefaultContext(),
            'item' => 'scope',
            'arguments' => [Context::USER_SCOPE, static fn () => null],
            'type' => Template::METHOD_CALL,
            'method' => 'scope',
        ];

        yield 'assign inherited from Struct' => [
            'object' => Context::createDefaultContext(),
            'item' => 'assign',
            'arguments' => [['scope' => Context::USER_SCOPE]],
            'type' => Template::METHOD_CALL,
            'method' => 'assign',
        ];

        yield 'setter on a source, which is no Struct' => [
            'object' => new AdminApiSource(null),
            'item' => 'setIsAdmin',
            'arguments' => [true],
            'type' => Template::METHOD_CALL,
            'method' => 'setIsAdmin',
        ];

        yield 'state flag on the sales channel context' => [
            'object' => Generator::generateSalesChannelContext(),
            'item' => 'addState',
            'arguments' => ['state'],
            'type' => Template::METHOD_CALL,
            'method' => 'addState',
        ];

        yield 'rule lock on the context' => [
            'object' => Context::createDefaultContext(),
            'item' => 'lockRules',
            'arguments' => [],
            'type' => Template::METHOD_CALL,
            'method' => 'lockRules',
        ];

        yield 'process-wide trust configuration on the request' => [
            'object' => new Request(),
            'item' => 'setTrustedProxies',
            'arguments' => [['0.0.0.0/0'], Request::HEADER_X_FORWARDED_FOR],
            'type' => Template::METHOD_CALL,
            'method' => 'setTrustedProxies',
        ];

        yield 'request rebuilt from the globals' => [
            'object' => new Request(),
            'item' => 'createFromGlobals',
            'arguments' => [],
            'type' => Template::METHOD_CALL,
            'method' => 'createFromGlobals',
        ];

        yield 'request copy without parentheses' => [
            'object' => new Request(),
            'item' => 'duplicate',
            'arguments' => [],
            'type' => Template::ANY_CALL,
            'method' => 'duplicate',
        ];

        yield 'setter called through attribute() on a Struct' => [
            'object' => Context::createDefaultContext(),
            'item' => 'setRuleIds',
            'arguments' => [['rule-id']],
            'type' => Template::ANY_CALL,
            'method' => 'setRuleIds',
        ];

        yield 'setter called through attribute() on a source' => [
            'object' => new AdminApiSource(null),
            'item' => 'setPermissions',
            'arguments' => [['tax:create']],
            'type' => Template::ANY_CALL,
            'method' => 'setPermissions',
        ];

        yield 'method name in a different case resolves to the declared method' => [
            'object' => Context::createDefaultContext(),
            'item' => 'SETRULEIDS',
            'arguments' => [['rule-id']],
            'type' => Template::METHOD_CALL,
            'method' => 'setRuleIds',
        ];
    }

    /**
     * @param list<mixed> $arguments
     */
    #[DataProvider('deniedContextCallProvider')]
    public function testTemplatesCannotChangeARequestContext(object $object, string $item, array $arguments, string $type, string $method): void
    {
        $this->expectExceptionObject(AdapterException::twigMethodCallNotAllowed($object::class, $method));

        SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, $item, $arguments, $type);
    }

    public function testDeniedCallLeavesTheContextUnchanged(): void
    {
        $context = Context::createDefaultContext();

        try {
            SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $context, 'setRuleIds', [['rule-id']], Template::ANY_CALL);
        } catch (AdapterException) {
        }

        static::assertSame([], $context->getRuleIds());
    }

    /**
     * @return \Generator<string, array{object: object, item: string, type: string, expected: mixed}>
     */
    public static function allowedContextReadProvider(): \Generator
    {
        yield 'getter call on the context' => [
            'object' => Context::createDefaultContext(),
            'item' => 'getScope',
            'type' => Template::METHOD_CALL,
            'expected' => Context::SYSTEM_SCOPE,
        ];

        yield 'property read prefers the getter over the scope() method' => [
            'object' => Context::createDefaultContext(),
            'item' => 'scope',
            'type' => Template::ANY_CALL,
            'expected' => Context::SYSTEM_SCOPE,
        ];

        yield 'read method without a get prefix' => [
            'object' => Context::createDefaultContext(),
            'item' => 'considerInheritance',
            'type' => Template::METHOD_CALL,
            'expected' => false,
        ];

        yield 'isser on a source, which is no Struct' => [
            'object' => new AdminApiSource(null),
            'item' => 'admin',
            'type' => Template::ANY_CALL,
            'expected' => false,
        ];

        yield 'getter on the request' => [
            'object' => new Request(),
            'item' => 'method',
            'type' => Template::ANY_CALL,
            'expected' => Request::METHOD_GET,
        ];

        yield 'read method of the request without a get prefix' => [
            'object' => new Request(),
            'item' => 'preferSafeContent',
            'type' => Template::METHOD_CALL,
            'expected' => false,
        ];
    }

    #[DataProvider('allowedContextReadProvider')]
    public function testTemplatesCanReadARequestContext(object $object, string $item, string $type, mixed $expected): void
    {
        static::assertSame($expected, SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $object, $item, type: $type));
    }

    public function testMethodOutsideTheDenyListIsCalled(): void
    {
        $context = Context::createDefaultContext();

        $result = SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $context, 'createWithVersionId', ['version-id'], Template::METHOD_CALL);

        static::assertInstanceOf(Context::class, $result);
        static::assertSame('version-id', $result->getVersionId());
    }

    public function testTemplatesCanStillExtendOtherStructs(): void
    {
        $page = new ArrayStruct();

        SwTwigFunction::getAttribute($this->environmentMock, new Source('', 'empty'), $page, 'addArrayExtension', ['myExtension', ['key' => 'value']], Template::METHOD_CALL);

        static::assertTrue($page->hasExtension('myExtension'));
    }

    public function testCallMacroReturnsNativeResultWithoutExplicitReturn(): void
    {
        static::assertSame('native result', SwTwigFunction::callMacro(static fn (): string => 'native result'));
    }

    public function testCallMacroWithReturnSupportReturnsExplicitNull(): void
    {
        $result = SwTwigFunction::callMacro(static function (): string {
            SwTwigFunction::returnFromMacro(null);

            return 'native result';
        });

        static::assertNull($result);
    }

    public function testCallMacroWithReturnSupportKeepsNestedReturnValuesSeparate(): void
    {
        $result = SwTwigFunction::callMacro(static function (): string {
            $nestedResult = SwTwigFunction::callMacro(static function (): string {
                SwTwigFunction::returnFromMacro(['nested result']);

                return 'nested native result';
            });

            SwTwigFunction::returnFromMacro($nestedResult);

            return 'native result';
        });

        static::assertSame(['nested result'], $result);
    }

    public function testCallMacroWithReturnSupportClearsReturnStateAfterException(): void
    {
        try {
            SwTwigFunction::callMacro(static function (): never {
                SwTwigFunction::returnFromMacro('stale result');

                throw new \RuntimeException('Macro failed');
            });
            static::fail('Expected macro exception');
        } catch (\RuntimeException $exception) {
            static::assertSame('Macro failed', $exception->getMessage());
        }

        static::assertSame('native result', SwTwigFunction::callMacro(static fn (): string => 'native result'));
    }

    public function testReturnOutsideMacroDoesNotLeakIntoNextCall(): void
    {
        SwTwigFunction::returnFromMacro('stale result');

        static::assertSame('native result', SwTwigFunction::callMacro(static fn (): string => 'native result'));
    }
}

/**
 * @internal
 */
class StructForTests extends Struct
{
    private bool $visible;

    private string $value;

    /**
     * @phpstan-ignore-next-line
     */
    private int $noGetter;

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function setNoGetter(int $noGetter): void
    {
        $this->noGetter = $noGetter;
    }
}
