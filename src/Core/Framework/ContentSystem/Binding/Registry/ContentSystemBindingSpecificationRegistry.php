<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Registry;

use Shopware\Core\Framework\ContentSystem\Binding\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Loader\AbstractContentSystemBindingSpecificationLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('framework')]
class ContentSystemBindingSpecificationRegistry extends AbstractContentSystemBindingSpecificationRegistry
{
    /**
     * @internal
     *
     * @param iterable<AbstractContentSystemBindingSpecificationLoader> $loaders
     */
    public function __construct(
        private readonly iterable $loaders,
    ) {
    }

    public function getDecorated(): AbstractContentSystemBindingSpecificationRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, BindingSpecification>
     */
    public function all(): array
    {
        $specifications = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $specification) {
                $specifications[$specification->source() . ':' . $specification->id()] = $specification;
            }
        }

        return $specifications;
    }
}
