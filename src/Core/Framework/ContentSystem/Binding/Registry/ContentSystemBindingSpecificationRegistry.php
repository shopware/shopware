<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Registry;

use Shopware\Core\Framework\ContentSystem\Binding\Loader\AbstractContentSystemBindingSpecificationLoader;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
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
     * Merges every loader's specifications into one map keyed by source-qualified id. A duplicate source-qualified
     * id across loaders throws; collisions are never reconciled.
     *
     * @return array<string, BindingSpecification>
     */
    public function all(): array
    {
        $specifications = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $specification) {
                $qualifiedId = $specification->qualifiedId();

                if (isset($specifications[$qualifiedId])) {
                    throw ContentSystemException::bindingSpecificationDuplicate(
                        $specification->id(),
                        $specifications[$qualifiedId]->source(),
                        $specification->source(),
                    );
                }

                $specifications[$qualifiedId] = $specification;
            }
        }

        return $specifications;
    }
}
