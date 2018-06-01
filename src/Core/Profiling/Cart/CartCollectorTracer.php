<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Cart;

use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Cart\Cart\CartCollectorInterface;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Framework\Struct\StructCollection;

class CartCollectorTracer implements CartCollectorInterface
{
    /**
     * @var CartCollectorInterface
     */
    private $decorated;

    /**
     * @var TracedCartActions
     */
    private $actions;

    public function __construct(CartCollectorInterface $decorated, TracedCartActions $actions)
    {
        $this->decorated = $decorated;
        $this->actions = $actions;
    }

    public function prepare(
        StructCollection $fetchDefinition,
        Cart $cart,
        CustomerContext $context
    ): void {
        $before = clone $fetchDefinition;
        $this->decorated->prepare($fetchDefinition, $cart, $context);

        $class = $this->getClassName($this->decorated);

        foreach ($fetchDefinition->getElements() as $key => $definition) {
            if (!$before->has($key)) {
                $this->actions->add(
                    $class,
                    [
                        'action' => 'Added fetch definition',
                        'before' => null,
                        'after' => null,
                        'item' => $definition,
                    ]
                );
            }
        }
    }

    public function fetch(
        StructCollection $dataCollection,
        StructCollection $fetchCollection,
        CustomerContext $context
    ): void {
        $before = clone $dataCollection;

        $time = microtime(true);

        $this->decorated->fetch($dataCollection, $fetchCollection, $context);

        $class = $this->getClassName($this->decorated);

        $time = microtime(true) - $time;

        $data = [];

        foreach ($dataCollection as $key => $value) {
            if (!$before->has($key)) {
                $data[] = $value;
            }
        }

        if (!empty($data)) {
            $this->actions->add($class, [
                'action' => 'Fected data within: (' . $time . ')',
                'before' => null,
                'after' => null,
                'item' => $data,
            ]);
        }
    }

    private function getClassName($instance)
    {
        $name = get_class($instance);
        $names = explode('\\', $name);

        return array_pop($names);
    }
}
