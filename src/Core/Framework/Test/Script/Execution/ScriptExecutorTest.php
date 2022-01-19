<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Script\Execution;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Exception\NoHookServiceFactoryException;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ScriptExecutorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private ScriptExecutor $executor;

    public function setUp(): void
    {
        $this->executor = $this->getContainer()->get(ScriptExecutor::class);
    }

    /**
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
        $translator->resetInMemoryCache();
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

    public function executeProvider()
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
    }
}
