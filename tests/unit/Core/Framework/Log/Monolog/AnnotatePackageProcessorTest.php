<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Log\Monolog;

use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Monolog\AnnotatePackageProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * @internal
 */
// @phpstan-ignore-next-line
#[Package('cause')]
#[CoversClass(AnnotatePackageProcessor::class)]
class AnnotatePackageProcessorTest extends TestCase
{
    public function testOnlyController(): void
    {
        $requestStack = new RequestStack();
        $inner = $this->createMock(AbstractHandler::class);
        $container = $this->createMock(ContainerInterface::class);
        $handler = new AnnotatePackageProcessor($requestStack, $container);

        $request = new Request();
        $request->attributes->set('_controller', TestController::class . '::load');
        $requestStack->push($request);

        $record = new LogRecord(
            new \DateTimeImmutable(),
            'business events',
            Level::Error,
            'Some message'
        );

        $expected = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $record->message,
            []
        );
        $expected->extra[Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = [
            'entrypoint' => 'controller',
        ];

        static::assertEquals($expected, $handler($record));
    }

    public function testOnlyControllerWithNonClassServiceId(): void
    {
        $requestStack = new RequestStack();
        $inner = $this->createMock(AbstractHandler::class);
        $container = $this->createMock(ContainerInterface::class);
        $handler = new AnnotatePackageProcessor($requestStack, $container);

        $request = new Request();
        $request->attributes->set('_controller', 'test.controller::load');
        $requestStack->push($request);
        $container->expects(static::once())
            ->method('get')
            ->with('test.controller', ContainerInterface::NULL_ON_INVALID_REFERENCE)
            ->willReturn(new TestController());

        $record = new LogRecord(
            new \DateTimeImmutable(),
            'business events',
            Level::Error,
            'Some message'
        );

        $expected = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $record->message,
            []
        );
        $expected->extra[Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = [
            'entrypoint' => 'controller',
        ];

        static::assertEquals($expected, $handler($record));
    }

    public function testOnlyControllerWithInvalidServiceId(): void
    {
        $requestStack = new RequestStack();
        $inner = $this->createMock(AbstractHandler::class);
        $container = $this->createMock(ContainerInterface::class);
        $handler = new AnnotatePackageProcessor($requestStack, $container);

        $request = new Request();
        $request->attributes->set('_controller', 'test.controller::load');
        $requestStack->push($request);
        $container->expects(static::once())
            ->method('get')
            ->with('test.controller', ContainerInterface::NULL_ON_INVALID_REFERENCE)
            ->willReturn(null);

        $record = new LogRecord(
            new \DateTimeImmutable(),
            'business events',
            Level::Error,
            'Some message'
        );

        static::assertEquals($record, $handler($record));
    }

    public function testExceptionInController(): void
    {
        $requestStack = new RequestStack();
        $inner = $this->createMock(AbstractHandler::class);
        $container = $this->createMock(ContainerInterface::class);
        $handler = new AnnotatePackageProcessor($requestStack, $container);

        $request = new Request();
        $request->attributes->set('_controller', TestController::class . '::load');
        $requestStack->push($request);

        try {
            throw new TestException('test');
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $context = [
            'exception' => $exception,
        ];

        $record = new LogRecord(
            new \DateTimeImmutable(),
            'business events',
            Level::Error,
            'Some message',
            $context
        );

        $expected = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $record->message,
            $context
        );
        $expected->extra[Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = [
            'entrypoint' => 'controller',
            'exception' => 'exception',
            'causingClass' => 'cause',
        ];

        static::assertEquals($expected, $handler($record));
    }

    public function testNoPackageAttributes(): void
    {
        $requestStack = new RequestStack();
        $inner = $this->createMock(AbstractHandler::class);
        $container = $this->createMock(ContainerInterface::class);
        $handler = new AnnotatePackageProcessor($requestStack, $container);

        $request = new Request();
        $request->attributes->set('_controller', TestControllerNoPackage::class . '::load');
        $requestStack->push($request);

        try {
            throw new TestExceptionNoPackage('test');
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $context = [
            'exception' => $exception,
        ];

        $record = new LogRecord(
            new \DateTimeImmutable(),
            'business events',
            Level::Error,
            'Some message',
            $context
        );

        $expected = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $record->message,
            $context
        );
        $expected->extra[Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = [
            'causingClass' => 'cause',
        ];

        static::assertEquals($expected, $handler($record));
    }

    public function testAnnotateCommand(): void
    {
        $exception = null;

        try {
            $command = new TestCommand();
            $command->run($this->createMock(InputInterface::class), $this->createMock(OutputInterface::class));
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $inner = $this->createMock(AbstractHandler::class);
        $container = $this->createMock(ContainerInterface::class);
        $handler = new AnnotatePackageProcessor($this->createMock(RequestStack::class), $container);

        $context = [
            'exception' => $exception,
            'dataIsPassedThru' => true,
        ];
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'business events',
            Level::Error,
            'Some message',
            $context
        );

        $expected = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $record->message,
            $context
        );
        $expected->extra[Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = [
            'entrypoint' => 'command',
            'exception' => 'exception',
            'causingClass' => 'command',
        ];

        static::assertEquals($expected, $handler($record));
    }

    public function testAnnotateCommandWithNestedException(): void
    {
        $exception = null;

        try {
            $command = new TestNestedCommand();
            $command->run($this->createMock(InputInterface::class), $this->createMock(OutputInterface::class));
        } catch (\Throwable $e) {
            $exception = $e;
        }

        $inner = $this->createMock(AbstractHandler::class);
        $container = $this->createMock(ContainerInterface::class);
        $handler = new AnnotatePackageProcessor($this->createMock(RequestStack::class), $container);

        $context = [
            'exception' => $exception,
            'dataIsPassedThru' => true,
        ];
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'business events',
            Level::Error,
            'Some message',
            $context
        );

        $expected = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $record->message,
            $context
        );
        $expected->extra[Package::PACKAGE_TRACE_ATTRIBUTE_KEY] = [
            'entrypoint' => 'command',
            'exception' => 'exception',
            'causingClass' => 'command',
        ];

        static::assertEquals($expected, $handler($record));
    }
}

/**
 * @internal
 */
// @phpstan-ignore-next-line
#[Package('controller')]
class TestController
{
    public function load(Request $request): Response
    {
        return new Response();
    }
}

/**
 * @internal
 */
class TestControllerNoPackage
{
    public function load(Request $request): Response
    {
        return new Response();
    }
}

/**
 * @internal
 */
// @phpstan-ignore-next-line
#[Package('exception')]
class TestException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return '500';
    }
}

/**
 * @internal
 */
class TestExceptionNoPackage extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return '500';
    }
}

/**
 * @internal
 */
// @phpstan-ignore-next-line
#[Package('command')]
class TestCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $testCause = new TestCause();
        $testCause->throw(new TestException('test'));

        return Command::SUCCESS;
    }
}

/**
 * @internal
 */
// @phpstan-ignore-next-line
#[Package('command')]
class TestNestedCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $testCause = new TestCause();
        $testCause->throw(new HandlerFailedException(new Envelope(new \stdClass()), [new TestException('test')]));

        return Command::SUCCESS;
    }
}

/**
 * @internal
 */
// @phpstan-ignore-next-line
#[Package('cause')]
class TestCause extends Command
{
    public function throw(\Throwable $exception): int
    {
        throw $exception;
    }
}
