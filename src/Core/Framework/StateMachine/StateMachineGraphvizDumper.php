<?php declare(strict_types=1);

namespace Shopware\Core\Framework\StateMachine;

use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateStruct;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionStruct;
use Shopware\Core\System\StateMachine\StateMachineStruct;

class StateMachineGraphvizDumper
{
    protected static $defaultOptions = [
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
     */
    public function dump(StateMachineStruct $stateMachine, array $options = [])
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
     * @internal
     */
    protected function findStates(StateMachineStruct $stateMachine): array
    {
        $states = [];

        /** @var StateMachineStateStruct $state */
        foreach ($stateMachine->getStates() as $state) {
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
     * @internal
     */
    protected function findTransitions(StateMachineStruct $stateMachine): array
    {
        $transitions = [];
        foreach ($stateMachine->getTransitions() as $transition) {
            $transitions[] = [
                'attributes' => ['shape' => 'box', 'regular' => true],
                'name' => $transition->getName(),
            ];
        }

        return $transitions;
    }

    protected function addStates(array $states): string
    {
        $code = '';
        foreach ($states as $id => $state) {
            $code .= sprintf("  place_%s [label=\"%s\", shape=circle%s];\n", $this->dotize($id), $this->escape($id), $this->addAttributes($state['attributes']));
        }

        return $code;
    }

    /**
     * @internal
     */
    protected function addTransitions(array $transitions): string
    {
        $code = '';
        foreach ($transitions as $place) {
            $code .= sprintf("  transition_%s [label=\"%s\", shape=box%s];\n", $this->dotize($place['name']), $this->escape($place['name']), $this->addAttributes($place['attributes']));
        }

        return $code;
    }

    /**
     * @internal
     */
    protected function startDot(array $options): string
    {
        return sprintf("digraph workflow {\n  %s\n  node [%s];\n  edge [%s];\n\n",
            $this->addOptions($options['graph']),
            $this->addOptions($options['node']),
            $this->addOptions($options['edge'])
        );
    }

    /**
     * @internal
     */
    protected function endDot(): string
    {
        return "}\n";
    }

    /**
     * @internal
     */
    protected function dotize($id): string
    {
        return hash('sha1', $id);
    }

    /**
     * @internal
     */
    protected function escape(string $string): string
    {
        return addslashes($string);
    }

    protected function findEdges(StateMachineStruct $stateMachine)
    {
        $edges = [];

        /** @var StateMachineTransitionStruct $transition */
        foreach ($stateMachine->getTransitions() as $transition) {
            $edges[$transition->getFromState()->getName()][] = [
                'name' => $transition->getActionName(),
                'to' => $transition->getToState()->getName(),
            ];
        }

        return $edges;
    }

    protected function addEdges(array $edges): string
    {
        $code = '';

        foreach ($edges as $id => $edges) {
            foreach ($edges as $edge) {
                $code .= sprintf("  place_%s -> place_%s [label=\"%s\" style=\"%s\"];\n", $this->dotize($id), $this->dotize($edge['to']), $this->escape($edge['name']), 'solid');
            }
        }

        return $code;
    }

    private function addAttributes(array $attributes): string
    {
        $code = [];
        foreach ($attributes as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $this->escape($v));
        }

        return $code ? ', ' . implode(', ', $code) : '';
    }

    private function addOptions(array $options): string
    {
        $code = [];
        foreach ($options as $k => $v) {
            $code[] = sprintf('%s="%s"', $k, $v);
        }

        return implode(' ', $code);
    }
}
