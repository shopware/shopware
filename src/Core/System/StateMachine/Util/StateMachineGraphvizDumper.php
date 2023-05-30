<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\StateMachineEntity;

#[Package('checkout')]
class StateMachineGraphvizDumper
{
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

    private function findStates(StateMachineEntity $stateMachine): array
    {
        $states = [];

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

    private function addStates(array $states): string
    {
        $code = '';
        foreach ($states as $id => $state) {
            $code .= sprintf("  place_%s [label=\"%s\", shape=circle%s];\n", $this->dotize($id), $this->escape($id), $this->addAttributes($state['attributes']));
        }

        return $code;
    }

    private function startDot(array $options): string
    {
        return sprintf(
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

    private function dotize($id): string
    {
        return hash('sha1', (string) $id);
    }

    private function escape(string $string): string
    {
        return addslashes($string);
    }

    private function findEdges(StateMachineEntity $stateMachine): array
    {
        $edges = [];

        foreach ($stateMachine->getTransitions() as $transition) {
            $edges[$transition->getFromStateMachineState()->getName()][] = [
                'name' => $transition->getActionName(),
                'to' => $transition->getToStateMachineState()->getName(),
            ];
        }

        return $edges;
    }

    private function addEdges(array $edges): string
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
            \assert(\is_string($k));
            \assert(\is_string($v));
            $code[] = sprintf('%s="%s"', $k, $v);
        }

        return implode(' ', $code);
    }
}
