<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\StateMachine\StateMachineEntity;

#[Package('checkout')]
class StateMachineGraphvizDumper
{
    /**
     * @var array<string, array<string, string|int|float>>
     */
    protected static array $defaultOptions = [
        'graph' => ['ratio' => 'compress', 'rankdir' => 'LR'],
        'node' => ['fontsize' => 9, 'fontname' => 'Arial', 'color' => '#333333', 'fillcolor' => 'lightblue', 'fixedsize' => 'false', 'width' => 1],
        'edge' => ['fontsize' => 9, 'fontname' => 'Arial', 'color' => '#333333', 'arrowhead' => 'normal', 'arrowsize' => 0.5],
    ];

    /**
     * Dumps the workflow as a graphviz graph.
     *
     * Available options:
     *
     *  * graph: The default options for the whole graph
     *  * node: The default options for nodes (places)
     *  * edge: The default options for edges
     *
     * @param array<string, mixed> $options
     */
    public function dump(StateMachineEntity $stateMachine, array $options = []): string
    {
        $places = $this->findStates($stateMachine);
        $edges = $this->findEdges($stateMachine);

        $options = array_replace_recursive(self::$defaultOptions, $options);

        return $this->startDot($options)
            . $this->addStates($places)
            . $this->addEdges($edges)
            . $this->endDot();
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function findStates(StateMachineEntity $stateMachine): array
    {
        $states = [];
        $stateMachineStates = $stateMachine->getStates();
        if ($stateMachineStates === null) {
            return $states;
        }

        foreach ($stateMachineStates as $state) {
            $attributes = [];
            if ($state->getId() === $stateMachine->getInitialStateId()) {
                $attributes['style'] = 'filled';
                $attributes['color'] = '#FF0000';
                $attributes['shape'] = 'doublecircle';
            }
            $states[$state->getName()] = [
                'attributes' => $attributes,
            ];
        }

        return $states;
    }

    /**
     * @param array<string, array<string, array<string, string>>> $states
     */
    private function addStates(array $states): string
    {
        $code = '';
        foreach ($states as $id => $state) {
            $code .= \sprintf("  place_%s [label=\"%s\", shape=circle%s];\n", $this->dotize($id), $this->escape($id), $this->addAttributes($state['attributes']));
        }

        return $code;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function startDot(array $options): string
    {
        return \sprintf(
            "digraph workflow {\n  %s\n  node [%s];\n  edge [%s];\n\n",
            $this->addOptions($options['graph']),
            $this->addOptions($options['node']),
            $this->addOptions($options['edge'])
        );
    }

    private function endDot(): string
    {
        return "}\n";
    }

    private function dotize(string $id): string
    {
        return Hasher::hash($id, 'sha1');
    }

    private function escape(string $string): string
    {
        return addslashes($string);
    }

    /**
     * @return array<string, array<array<string, string>>>
     */
    private function findEdges(StateMachineEntity $stateMachine): array
    {
        $edges = [];
        $transitions = $stateMachine->getTransitions();
        if ($transitions === null) {
            return $edges;
        }

        foreach ($transitions as $transition) {
            $fromStateMachineState = $transition->getFromStateMachineState();
            $toStateMachineState = $transition->getToStateMachineState();
            if ($fromStateMachineState === null || $toStateMachineState === null) {
                continue;
            }
            $edges[$fromStateMachineState->getName()][] = [
                'name' => $transition->getActionName(),
                'to' => $toStateMachineState->getName(),
            ];
        }

        return $edges;
    }

    /**
     * @param array<string, array<array<string, string>>> $edges
     */
    private function addEdges(array $edges): string
    {
        $code = '';

        foreach ($edges as $id => $edgeCases) {
            foreach ($edgeCases as $edge) {
                $code .= \sprintf("  place_%s -> place_%s [label=\"%s\" style=\"%s\"];\n", $this->dotize($id), $this->dotize($edge['to']), $this->escape($edge['name']), 'solid');
            }
        }

        return $code;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function addAttributes(array $attributes): string
    {
        $code = [];
        foreach ($attributes as $k => $v) {
            $code[] = \sprintf('%s="%s"', $k, $this->escape($v));
        }

        return $code ? ', ' . implode(', ', $code) : '';
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function addOptions(array $options): string
    {
        $code = [];
        foreach ($options as $k => $v) {
            \assert($k === null || is_scalar($k) || $k instanceof \Stringable);
            \assert($v === null || is_scalar($v) || $v instanceof \Stringable);
            $code[] = \sprintf('%s="%s"', $k, $v);
        }

        return implode(' ', $code);
    }
}
