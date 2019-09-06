<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Doctrine;

use Doctrine\DBAL\Logging\DebugStack as DoctrineDebugStack;

/**
 * Includes executed SQLs in a Debug Stack.
 */
class DebugStack extends DoctrineDebugStack
{
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
            if (!is_array($caller)) {
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
}
