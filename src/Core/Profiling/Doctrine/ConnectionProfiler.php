<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Doctrine;

use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Shopware\Core\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

class ConnectionProfiler implements DataCollectorInterface
{
    private array $data = [];

    /**
     * @var DebugStack|null
     */
    private $logger;

    public function __construct()
    {
        $logger = Kernel::getConnection()->getConfiguration()->getSQLLogger();
        if (!$logger instanceof DebugStack) {
            return;
        }

        $this->logger = $logger;
    }

    public function getName()
    {
        return 'app.connection_collector';
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        if (!$this->logger || !$this->logger instanceof DebugStack) {
            $this->data['queries'] = [];

            return;
        }

        $queries = $this->sanitizeQueries($this->logger->queries);

        $this->data = ['queries' => $queries];
    }

    public function reset(): void
    {
        $this->data = [
            'queries' => [],
        ];
        if (!$this->logger) {
            return;
        }

        $this->logger->queries = [];
        $this->logger->currentQuery = 0;
    }

    public function getQueryCount()
    {
        return \count($this->data['queries']);
    }

    public function getQueries()
    {
        return $this->data['queries'];
    }

    public function getTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $query) {
            $time += $query['executionMS'];
        }

        return $time;
    }

    private function sanitizeQueries(array $queries)
    {
        foreach ($queries as $i => $query) {
            $queries[$i] = $this->sanitizeQuery($query);
        }

        return $queries;
    }

    private function sanitizeQuery($query)
    {
        $query['explainable'] = true;
        if ($query['params'] === null) {
            $query['params'] = [];
        }
        if (!\is_array($query['params'])) {
            $query['params'] = [$query['params']];
        }
        foreach ($query['params'] as $j => $param) {
            if (isset($query['types'][$j])) {
                // Transform the param according to the type
                $type = $query['types'][$j];
                if (\is_string($type)) {
                    $type = Type::getType($type);
                }
                if ($type instanceof Type) {
                    $query['types'][$j] = $type->getBindingType();

                    try {
                        $param = $type->convertToDatabaseValue($param, Kernel::getConnection()->getDatabasePlatform());
                    } catch (\TypeError $e) {
                        // Error thrown while processing params, query is not explainable.
                        $query['explainable'] = false;
                    } catch (ConversionException $e) {
                        $query['explainable'] = false;
                    }
                }
            }

            list($query['params'][$j], $explainable) = $this->sanitizeParam($param);
            if (!$explainable) {
                $query['explainable'] = false;
            }
        }

        return $query;
    }

    /**
     * Sanitizes a param.
     *
     * The return value is an array with the sanitized value and a boolean
     * indicating if the original value was kept (allowing to use the sanitized
     * value to explain the query).
     */
    private function sanitizeParam($var): array
    {
        if (\is_object($var)) {
            $className = \get_class($var);

            return method_exists($var, '__toString')
                ? [sprintf('/* Object(%s): */"%s"', $className, $var->__toString()), false]
                : [sprintf('/* Object(%s) */', $className), false];
        }

        if (\is_array($var)) {
            $a = [];
            $original = true;
            foreach ($var as $k => $v) {
                list($value, $orig) = $this->sanitizeParam($v);
                $original = $original && $orig;
                $a[$k] = $value;
            }

            return [$a, $original];
        }

        if (\is_resource($var)) {
            return [sprintf('/* Resource(%s) */', get_resource_type($var)), false];
        }

        return [$var, true];
    }
}
