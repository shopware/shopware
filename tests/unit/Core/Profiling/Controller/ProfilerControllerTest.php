<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Controller;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Profiling\Controller\ProfilerController;
use Shopware\Core\Profiling\Doctrine\BacktraceDebugDataHolder;
use Shopware\Core\Profiling\Doctrine\ConnectionProfiler;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(ProfilerController::class)]
class ProfilerControllerTest extends TestCase
{
    public function testErrorIsReturnedIfProfileDoesNotExist(): void
    {
        $twig = $this->createMock(Environment::class);
        $profiler = $this->createMock(Profiler::class);
        $connection = $this->createMock(Connection::class);
        $controller = new ProfilerController($twig, $profiler, $connection);

        $profiler->expects(static::once())
            ->method('loadProfile')
            ->with('some-token')
            ->willReturn(null);

        $response = $controller->explainAction('some-token', 'some-panel', 'default', 5);
        static::assertEquals('This profile does not exist.', $response->getContent());
    }

    public function testErrorIsReturnedIfPanelDoesNotExist(): void
    {
        $twig = $this->createMock(Environment::class);
        $profiler = $this->createMock(Profiler::class);
        $connection = $this->createMock(Connection::class);
        $controller = new ProfilerController($twig, $profiler, $connection);

        $profile = new Profile('some-token');
        $profiler->expects(static::once())
            ->method('loadProfile')
            ->with('some-token')
            ->willReturn($profile);

        $response = $controller->explainAction('some-token', 'some-panel', 'default', 5);
        static::assertEquals('This collector does not exist.', $response->getContent());
    }

    public function testErrorIsReturnedIfPanelIsIncorrect(): void
    {
        $twig = $this->createMock(Environment::class);
        $profiler = $this->createMock(Profiler::class);
        $connection = $this->createMock(Connection::class);
        $controller = new ProfilerController($twig, $profiler, $connection);

        $profile = new Profile('some-token');
        $profiler->expects(static::once())
            ->method('loadProfile')
            ->with('some-token')
            ->willReturn($profile);

        $profile->addCollector(new class() implements DataCollectorInterface {
            public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
            {
                // noop
            }

            public function getName(): string
            {
                return 'some-panel';
            }

            public function reset(): void
            {
                // noop
            }
        });

        $response = $controller->explainAction('some-token', 'some-panel', 'default', 5);
        static::assertEquals('This collector does not exist.', $response->getContent());
    }

    public function testErrorIsReturnedIfQueryDoesNotExist(): void
    {
        $config = (new Configuration())
            ->setMiddlewares([new ProfilingMiddleware()]);

        $twig = $this->createMock(Environment::class);
        $profiler = $this->createMock(Profiler::class);
        $connection = $this->createMock(Connection::class);

        $connection->expects(static::any())
            ->method('getConfiguration')
            ->willReturn($config);

        $controller = new ProfilerController($twig, $profiler, $connection);

        $profile = new Profile('some-token');
        $profiler->expects(static::once())
            ->method('loadProfile')
            ->with('some-token')
            ->willReturn($profile);

        $collector = new ConnectionProfiler($connection);
        $collector->lateCollect();

        $profile->addCollector($collector);

        $response = $controller->explainAction(
            'some-token',
            'app.connection_collector',
            'default',
            5
        );

        static::assertEquals('This query does not exist.', $response->getContent());
    }

    public function testErrorIsReturnedIfQueryIsNotExplainable(): void
    {
        $debugDataHolder = new BacktraceDebugDataHolder(['default']);
        $config = (new Configuration())
            ->setMiddlewares([new ProfilingMiddleware($debugDataHolder)]);

        $twig = $this->createMock(Environment::class);
        $profiler = $this->createMock(Profiler::class);
        $connection = $this->createMock(Connection::class);

        $connection
            ->method('getConfiguration')
            ->willReturn($config);

        $controller = new ProfilerController($twig, $profiler, $connection);

        $profile = new Profile('some-token');
        $profiler->expects(static::once())
            ->method('loadProfile')
            ->with('some-token')
            ->willReturn($profile);

        $query = new Query('select * from table where key = ?');
        $query->setValue(
            1,
            new class() {
                public function __toString(): string
                {
                    return 'value';
                }
            },
            ParameterType::STRING
        );
        $debugDataHolder->addQuery('default', $query);

        $collector = new ConnectionProfiler($connection);
        $collector->lateCollect();

        $profile->addCollector($collector);

        $response = $controller->explainAction(
            'some-token',
            'app.connection_collector',
            'default',
            0
        );

        static::assertEquals('This query cannot be explained.', $response->getContent());
    }

    public function testExplainQuery(): void
    {
        $debugDataHolder = new BacktraceDebugDataHolder(['default']);
        $config = (new Configuration())
            ->setMiddlewares([new ProfilingMiddleware($debugDataHolder)]);

        $twig = $this->createMock(Environment::class);
        $profiler = $this->createMock(Profiler::class);
        $connection = $this->createMock(Connection::class);

        $connection
            ->method('getConfiguration')
            ->willReturn($config);

        $connection
            ->expects(static::once())
            ->method('executeQuery')
            ->with('EXPLAIN SELECT 1', [], []);

        $controller = new ProfilerController($twig, $profiler, $connection);

        $profile = new Profile('some-token');
        $profiler->expects(static::once())
            ->method('loadProfile')
            ->with('some-token')
            ->willReturn($profile);

        $debugDataHolder->addQuery('default', new Query('SELECT 1'));

        $collector = new ConnectionProfiler($connection);
        $collector->lateCollect();

        $profile->addCollector($collector);

        $response = $controller->explainAction(
            'some-token',
            'app.connection_collector',
            'default',
            0
        );

        static::assertEquals('', $response->getContent());
    }
}
