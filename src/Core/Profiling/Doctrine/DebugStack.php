<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Doctrine;

use Doctrine\DBAL\Logging\DebugStack as DoctrineDebugStack;

/**
 * Includes executed SQLs in a Debug Stack.
 */
class DebugStack extends DoctrineDebugStack
{
    public static string $writeSqlRegex = '/^\s*(UPDATE|ALTER|BACKUP|CREATE|DELETE|DROP|EXEC|INSERT|TRUNCATE)/';

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->ensureMasterSlaveCompatibility($sql);
        parent::startQuery($sql, $params, $types);
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery(): void
    {
        parent::stopQuery();
        if (!$this->enabled) {
            return;
        }

        $stack = debug_backtrace();

        $stack = array_map(function ($caller) {
            if (!\is_array($caller)) {
                return null;
            }
            if (!isset($caller['class']) || !isset($caller['function']) || !isset($caller['line'])) {
                return null;
            }

            return $caller['class'] . '::' . $caller['function'] . ' (line ' . $caller['line'] . ')';
        }, $stack);

        $stack = array_filter($stack);

        $this->queries[$this->currentQuery]['stack'] = $stack;
    }

    private function ensureMasterSlaveCompatibility(string $query): void
    {
        $sqlMethod = debug_backtrace()[2]['function'];
        if ($sqlMethod !== 'executeQuery') {
            return;
        }

        $matches = preg_match_all(self::$writeSqlRegex, $query);

        if ($matches) {
            throw new \RuntimeException(
                sprintf('Write operations are not supported when using executeQuery, use executeStatement instead. Query: %s', $query)
            );
        }
    }
}
