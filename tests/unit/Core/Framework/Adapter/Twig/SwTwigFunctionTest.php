<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Test\Generator;
use Twig\Environment;
use Twig\Source;
use Twig\Template;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SwTwigFunction::class)]
class SwTwigFunctionTest extends TestCase
{
    private Stub&Environment $environment;

    protected function setUp(): void
    {
        $this->environment = static::createStub(Environment::class);
    }

    /**
     * @return \Generator<string, array{object: Struct, attribute: string, expected: string|bool|null, arguments?: array}>
     */
    public static function getAttributeDataProvider(): \Generator
    {
        $object = new StructForTests();
        $object->setNoGetter(99);
        $object->setValue('valueValue');
        $object->setVisible(true);

        yield 'null value' => [
            'object' => new ArrayStruct(['test' => null]),
            'attribute' => 'test',
            'expected' => null,
        ];

        yield 'boolean true' => [
            'object' => new ArrayStruct(['test' => true]),
            'attribute' => 'test',
            'expected' => true,
        ];

        yield 'boolean false' => [
            'object' => new ArrayStruct(['test' => false]),
            'attribute' => 'test',
            'expected' => false,
        ];

        yield 'just property' => [
            'object' => new ArrayStruct(['test' => 'value']),
            'attribute' => 'test',
            'expected' => 'value',
        ];

        yield 'getter method' => [
            'object' => $object,
            'attribute' => 'value',
            'expected' => 'valueValue',
        ];

        yield 'isVisible method' => [
            'object' => $object,
            'attribute' => 'isVisible',
            'expected' => true,
        ];

        yield 'method with arguments' => [
            'object' => $object,
            'attribute' => 'getNonExistentProperty',
            'arguments' => ['arg1', 'arg2'],
            'expected' => 'result',
        ];

        yield 'hasser method' => [
            'object' => $object,
            'attribute' => 'children',
            'expected' => true,
        ];

        yield 'isser method takes precedence over hasser method' => [
            'object' => $object,
            'attribute' => 'variants',
            'expected' => false,
        ];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('getAttributeDataProvider')]
    public function testGetAttributeWithVariousInputs(Struct $object, string $attribute, string|bool|null $expected, array $arguments = []): void
    {
        $result = SwTwigFunction::getAttribute(
            $this->environment,
            new Source('', 'empty'),
            $object,
            $attribute,
            $arguments
        );

        static::assertSame($expected, $result);
    }

    public function testGetAttributeFallsBackToCoreExtensionWhenMethodThrows(): void
    {
        $source = new Source('', 'test_template');

        $this->expectExceptionObject(new \RuntimeException('Test exception'));

        $struct = new StructForTests();
        $struct->setThrowException(true);

        SwTwigFunction::getAttribute(
            $this->environment,
            $source,
            $struct,
            'nonExistentProperty'
        );
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

        yield 'callback with extra permissions on the sales channel context' => [
            'object' => Generator::generateSalesChannelContext(),
            'item' => 'withPermissions',
            'arguments' => [['permission' => true], static fn () => null],
            'type' => Template::METHOD_CALL,
            'method' => 'withPermissions',
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

        SwTwigFunction::getAttribute($this->environment, new Source('', 'empty'), $object, $item, $arguments, $type);
    }

    public function testDeniedCallLeavesTheContextUnchanged(): void
    {
        $context = Context::createDefaultContext();

        try {
            SwTwigFunction::getAttribute($this->environment, new Source('', 'empty'), $context, 'setRuleIds', [['rule-id']], Template::ANY_CALL);
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
    }

    #[DataProvider('allowedContextReadProvider')]
    public function testTemplatesCanReadARequestContext(object $object, string $item, string $type, mixed $expected): void
    {
        static::assertSame($expected, SwTwigFunction::getAttribute($this->environment, new Source('', 'empty'), $object, $item, type: $type));
    }

    public function testMethodOutsideTheDenyListIsCalled(): void
    {
        $context = Context::createDefaultContext();

        $result = SwTwigFunction::getAttribute($this->environment, new Source('', 'empty'), $context, 'createWithVersionId', ['version-id'], Template::METHOD_CALL);

        static::assertInstanceOf(Context::class, $result);
        static::assertSame('version-id', $result->getVersionId());
    }

    public function testTemplatesCanStillExtendOtherStructs(): void
    {
        $page = new ArrayStruct();

        SwTwigFunction::getAttribute($this->environment, new Source('', 'empty'), $page, 'addArrayExtension', ['myExtension', ['key' => 'value']], Template::METHOD_CALL);

        static::assertTrue($page->hasExtension('myExtension'));
    }

    public function testCallMacroReturnsNativeResultWithoutExplicitReturn(): void
    {
        static::assertSame('native result', SwTwigFunction::callMacro(static fn (): string => 'native result'));
    }

    public function testCallMacroReturnsExplicitNull(): void
    {
        $result = SwTwigFunction::callMacro(static function (): string {
            SwTwigFunction::returnFromMacro(null);

            return 'native result';
        });

        static::assertNull($result);
    }

    public function testCallMacroKeepsNestedReturnValuesSeparate(): void
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

    public function testCallMacroClearsReturnStateAfterException(): void
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

    private int $noGetter;

    private bool $throwException = false;

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
        if ($this->noGetter > 0) {
            $this->visible = true;
        }
    }

    public function setThrowException(bool $throwException): void
    {
        $this->throwException = $throwException;
    }

    public function getNonExistentProperty(): string
    {
        if ($this->throwException) {
            throw new \RuntimeException('Test exception');
        }

        return 'result';
    }

    public function hasChildren(): bool
    {
        return true;
    }

    public function isVariants(): bool
    {
        return false;
    }

    public function hasVariants(): bool
    {
        return true;
    }
}
