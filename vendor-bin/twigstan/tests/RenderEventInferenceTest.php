<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use Nette\Neon\Neon;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\Output;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @phpstan-import-type Write from RenderEventParameterCollector
 *
 * @internal
 */
#[Package('framework')]
class RenderEventInferenceTest extends TestCase
{
    private Filesystem $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = $this->filesystem->tempnam(sys_get_temp_dir(), 'twigstan-listeners-');
        $this->filesystem->remove($this->directory);
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testIncompleteAnalysisDoesNotPublishListenerTypes(): void
    {
        // @phpstan-ignore phpstanApi.constructor (Test fixture for the pinned PHPStan version.)
        $analysis = new AnalysisResult([], ['Analysis failed'], [], [], [], true, null, false, 0, false);
        $file = $this->directory . '/writes.json';

        static::assertSame(2, (new RenderEventDataFormatter($file))->formatErrors($analysis, static::createStub(Output::class)));
        static::assertFileDoesNotExist($file);
    }

    public function testEmptyAnalysisDoesNotPublishListenerTypes(): void
    {
        // @phpstan-ignore phpstanApi.constructor (Test fixture for the pinned PHPStan version.)
        $analysis = new AnalysisResult([], [], [], [], [], true, null, false, 0, false);
        $file = $this->directory . '/writes.json';

        static::assertSame(2, (new RenderEventDataFormatter($file))->formatErrors($analysis, static::createStub(Output::class)));
        static::assertFileDoesNotExist($file);
    }

    public function testInfersRegisteredListenerWritesAndReportsUnsupportedEffects(): void
    {
        $root = \dirname(__DIR__, 3);
        $listeners = [];
        foreach (['unconditional', 'conditional', 'forTemplate', 'dynamicKey', 'helper', 'alias', 'untyped'] as $method) {
            $listeners[] = ['callback' => RenderInferenceFixture::class . '::' . $method, 'parameter' => 'event'];
        }
        $this->filesystem->dumpFile($this->directory . '/listeners.json', json_encode($listeners, \JSON_THROW_ON_ERROR));
        $this->filesystem->dumpFile($this->directory . '/phpstan.neon', Neon::encode([
            'parameters' => [
                'level' => null,
                'customRulesetUsed' => true,
                'reportUnmatchedIgnoredErrors' => false,
                'paths' => [__FILE__],
                'tmpDir' => $this->directory . '/phpstan',
                'parallel' => ['maximumNumberOfProcesses' => 1],
            ],
            'services' => [
                [
                    'class' => RenderEventParameterCollector::class,
                    'arguments' => ['manifestFile' => $this->directory . '/listeners.json'],
                    'tags' => ['phpstan.collector', 'phpstan.parser.richParserNodeVisitor'],
                ],
                'errorFormatter.renderEventData' => [
                    'class' => RenderEventDataFormatter::class,
                    'arguments' => ['outputFile' => $this->directory . '/writes.json'],
                ],
            ],
        ], true));

        $process = new Process([\PHP_BINARY, $root . '/vendor-bin/twigstan/bin/phpstan', 'analyse', '--configuration=' . $this->directory . '/phpstan.neon', '--error-format=renderEventData', '--no-progress'], $root, timeout: 60);
        $process->run();
        static::assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        /** @var list<Write> $writes */
        $writes = json_decode($this->filesystem->readFile($this->directory . '/writes.json'), true, flags: \JSON_THROW_ON_ERROR);
        $parameters = array_column(array_filter($writes, static fn (array $write): bool => $write['kind'] === 'parameter'), null, 'key');

        static::assertSame(['first', 'conditional', 'late', 'common', 'target', 'untyped'], array_keys($parameters));
        static::assertSame('\\stdClass', $parameters['first']['type']);
        static::assertFalse($parameters['first']['optional']);
        static::assertTrue($parameters['conditional']['optional']);
        static::assertTrue($parameters['late']['optional']);
        static::assertSame(['product.twig'], $parameters['target']['views']);
        static::assertSame([], $parameters['common']['views']);
        static::assertFalse($parameters['common']['optional']);
        static::assertSame('123', $parameters['untyped']['type']);
        static::assertCount(3, array_filter($writes, static fn (array $write): bool => $write['kind'] === 'unresolved'));
    }
}

/**
 * @internal
 */
#[Package('framework')]
class RenderInferenceFixture
{
    public function unconditional(StorefrontRenderEvent $event): void
    {
        $event->setParameter(value: new \stdClass(), key: 'first');
    }

    public function conditional(StorefrontRenderEvent $event, bool $enabled): void
    {
        if ($enabled) {
            $event->setParameter('conditional', 42);
        }
        if (!$enabled) {
            return;
        }
        $event->setParameter('late', 'value');
    }

    public function forTemplate(StorefrontRenderEvent $event): void
    {
        $event->setParameter('common', true);
        if ($event->getView() !== 'product.twig') {
            return;
        }
        $event->setParameter('target', 123);
    }

    public function dynamicKey(StorefrontRenderEvent $event, string $key): void
    {
        $event->setParameter($key, 42);
    }

    public function helper(StorefrontRenderEvent $event): void
    {
        $this->notRegistered($event);
    }

    public function notRegistered(StorefrontRenderEvent $event): void
    {
        $event->setParameter('notRegistered', true);
    }

    public function alias(StorefrontRenderEvent $event): void
    {
        $alias = $event;
        $alias->setParameter('alias', true);
    }

    public function untyped(mixed $event): void
    {
        $event->setParameter('untyped', 123);
    }
}
