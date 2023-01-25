<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Doctrine\ConnectionProfiler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\VarDumper\Cloner\Data;
use Twig\Environment;

/**
 * @internal
 */
#[Package('core')]
class ProfilerController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly Profiler $profiler,
        private readonly Connection $connection
    ) {
    }

    public function explainAction(
        string $profilerToken,
        string $panelName,
        string $connectionName,
        int $queryIndex
    ): Response {
        $this->profiler->disable();

        $profile = $this->profiler->loadProfile($profilerToken);

        if (!$profile) {
            return new Response('This profile does not exist.');
        }

        try {
            $collector = $profile->getCollector($panelName);
        } catch (\InvalidArgumentException) {
            return new Response('This collector does not exist.');
        }

        if (!$collector instanceof ConnectionProfiler) {
            return new Response('This collector does not exist.');
        }

        $queries = $collector->getQueries();

        if (!isset($queries[$connectionName][$queryIndex])) {
            return new Response('This query does not exist.');
        }

        $queryIndex = $queries[$connectionName][$queryIndex];
        if (!$queryIndex['explainable']) {
            return new Response('This query cannot be explained.');
        }

        try {
            $results = $this->explain($this->connection, $queryIndex);
        } catch (\Throwable) {
            return new Response('This query cannot be explained.');
        }

        return new Response($this->twig->render('@Profiling/Collector/explain.html.twig', [
            'data' => $results,
            'query' => $queryIndex,
        ]));
    }

    /**
     * @param array<mixed> $query
     *
     * @return array<mixed>
     */
    private function explain(Connection $connection, array $query): array
    {
        $params = $query['params'];

        if ($params instanceof Data) {
            $params = $params->getValue(true);
        }

        return $connection->executeQuery('EXPLAIN ' . $query['sql'], $params, $query['types'])->fetchAllAssociative();
    }
}
