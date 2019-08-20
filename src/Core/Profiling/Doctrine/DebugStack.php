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
    public function stopQuery()
    {
        parent::stopQuery();
        if (!$this->enabled) {
            return;
        }

        $stack = debug_backtrace();

        $stack = array_map(function ($caller) {
            return $caller['class'] . '::' . $caller['function'] . ' (line ' . $caller['line'] . ')';
        }, $stack);

        $this->queries[$this->currentQuery]['stack'] = $stack;
    }
}
