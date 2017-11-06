<?php declare(strict_types=1);

namespace Shopware\Traceable\Cart;

use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\CollectorInterface;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\IndexedCollection;

class CollectorTracer implements CollectorInterface
{
    /**
     * @var CollectorInterface
     */
    private $decorated;

    /**
     * @var TracedCartActions
     */
    private $actions;

    public function __construct(CollectorInterface $decorated, TracedCartActions $actions)
    {
        $this->decorated = $decorated;
        $this->actions = $actions;
    }

    public function prepare(
        IndexedCollection $fetchDefinition,
        CartContainer $cartContainer,
        ShopContext $context
    ): void {
        $before = clone $fetchDefinition;
        $this->decorated->prepare($fetchDefinition, $cartContainer, $context);

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
        IndexedCollection $dataCollection,
        IndexedCollection $fetchCollection,
        ShopContext $context
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
