<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader\AbstractContentSystemStyleOptionLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('framework')]
class ContentSystemStyleOptionRegistry extends AbstractContentSystemStyleOptionRegistry
{
    /**
     * @internal
     *
     * @param iterable<AbstractContentSystemStyleOptionLoader> $loaders
     */
    public function __construct(
        private readonly iterable $loaders,
    ) {
    }

    public function getDecorated(): AbstractContentSystemStyleOptionRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, StyleOptionSpecification>
     */
    public function all(): array
    {
        $options = [];

        // Cross-loader dedup: individual loaders guarantee internal uniqueness, this catches collisions across loaders
        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $specification) {
                $name = $specification->name();

                if (\array_key_exists($name, $options)) {
                    throw ContentSystemException::styleOptionDuplicate(
                        $name,
                        $options[$name]->source(),
                        $specification->source(),
                    );
                }

                $options[$name] = $specification;
            }
        }

        return $options;
    }
}
