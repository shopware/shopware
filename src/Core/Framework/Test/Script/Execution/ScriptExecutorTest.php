<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Exception\NoHookServiceFactoryException;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\SalesChannelRequest;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class ScriptExecutorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private ScriptExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = $this->getContainer()->get(ScriptExecutor::class);
    }

    /**
     * @param array<string> $hooks
     * @param array<string, mixed> $expected
     *
     * @dataProvider executeProvider
     */
    public function testExecute(array $hooks, array $expected): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $object = new ArrayStruct();

        $context = Context::createDefaultContext();
        foreach ($hooks as $hook) {
            $this->executor->execute(new TestHook($hook, $context, ['object' => $object]));
        }

        static::assertNotEmpty($expected);

        foreach ($expected as $key => $value) {
            static::assertTrue($object->has($key));
            static::assertEquals($value, $object->get($key));
        }
    }

    public function testNoneExistingServicesRequired(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->expectException(ScriptExecutionFailedException::class);
        $this->expectExceptionMessage('The service "Hook: simple-function-case" has a dependency on a non-existent service "none-existing"');

        $this->executor->execute(new TestHook('simple-function-case', Context::createDefaultContext(), [], ['none-existing']));
    }

    public function testHookAwareServiceValidation(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $this->expectException(ScriptExecutionFailedException::class);
        $innerException = new NoHookServiceFactoryException('product.repository');

        $this->expectExceptionMessage($innerException->getMessage());

        $this->executor->execute(new TestHook('simple-function-case', Context::createDefaultContext(), [], ['product.repository']));
    }

    public function testTranslation(): void
    {
        $translator = $this->getContainer()->get(Translator::class);
        $translator->reset();
        $translator->warmUp('');

        $context = Context::createDefaultContext();

        $snippet = [
            'translationKey' => 'new.unit.test.key',
            'value' => 'Realisiert mit Unit test',
            'setId' => $this->getSnippetSetIdForLocale('en-GB'),
            'author' => 'Shopware',
        ];
        $this->getContainer()->get('snippet.repository')->create([$snippet], $context);

        // fake request
        $request = new Request();

        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $this->getSnippetSetIdForLocale('en-GB'));
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, 'en-GB');

        $this->getContainer()->get(RequestStack::class)->push($request);

        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $object = new ArrayStruct();
        $this->executor->execute(new TestHook('translation-case', $context, ['object' => $object]));

        static::assertSame('Realisiert mit Unit test', $object->get('translated'));
    }

    public function testStoppableHooksStopsPropagation(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $object = new ArrayStruct();

        $context = Context::createDefaultContext();
        $this->executor->execute(new StoppableTestHook('stoppable-case', $context, ['object' => $object]));

        static::assertEquals([
            'first-script' => 'called',
            'second-script' => 'called',
        ], $object->all());
    }

    public function testExecuteDeprecatedHookTriggersDeprecation(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $object = new ArrayStruct();

        $context = Context::createDefaultContext();
        $this->executor->execute(new DeprecatedTestHook('simple-function-case', $context, ['object' => $object]));

        static::assertTrue($object->has('foo'));
        static::assertEquals('bar', $object->get('foo'));

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('simple-function-case', $traces);
        static::assertCount(1, $traces['simple-function-case'][0]['deprecations']);
        static::assertEquals([
            DeprecatedTestHook::getDeprecationNotice() => 1,
        ], $traces['simple-function-case'][0]['deprecations']);
    }

    public function testAccessDeprecatedServiceOfHookTriggersDeprecation(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $context = Context::createDefaultContext();
        $this->executor->execute(new TestHook(
            'simple-service-script',
            $context,
            [],
            [RepositoryFacadeHookFactory::class],
            [RepositoryFacadeHookFactory::class => 'The `repository` service is deprecated for testing purposes.']
        ));

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('simple-service-script', $traces);
        static::assertArrayHasKey('The `repository` service is deprecated for testing purposes.', $traces['simple-service-script'][0]['deprecations']);
        static::assertEquals(2, $traces['simple-service-script'][0]['deprecations']['The `repository` service is deprecated for testing purposes.']);
    }

    public function testNotImplementingAFunctionThatWillBeRequiredTriggersException(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');

        $object = new ArrayStruct();

        $context = Context::createDefaultContext();
        $this->executor->execute(new FunctionWillBeRequiredTestHook(
            'simple-function-case',
            $context,
            ['object' => $object],
        ));

        $traces = $this->getScriptTraces();
        static::assertArrayHasKey('simple-function-case::test', $traces);
        static::assertCount(1, $traces['simple-function-case::test'][0]['deprecations']);
        static::assertEquals([
            'Function "test" will be required from v6.5.0.0 onward, but is not implemented in script "simple-function-case/simple-function-case.twig", please make sure you add the block in your script.' => 1,
        ], $traces['simple-function-case::test'][0]['deprecations']);
    }

    /**
     * @return array<string, array{0: array<string>, 1: array<string, mixed>}>
     */
    public static function executeProvider(): iterable
    {
        yield 'Test simple function call' => [
            ['simple-function-case'],
            ['foo' => 'bar'],
        ];
        yield 'Test multiple scripts called' => [
            ['simple-function-case', 'multi-script-case'],
            ['foo' => 'bar', 'bar' => 'foo', 'baz' => 'foo'],
        ];
        yield 'Test include with function call' => [
            ['include-case'],
            ['called' => 1],
        ];
        yield 'Test get shopware version' => [
            ['shopware-version-case'],
            [
                'version' => Kernel::SHOPWARE_FALLBACK_VERSION,
                'version_compare' => true,
            ],
        ];
    }
}
