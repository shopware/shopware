<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Registry;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentSystemElementTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('framework')]
class ContentSystemElementTypeRegistry extends AbstractContentSystemElementTypeRegistry
{
    /**
     * @internal
     *
     * @param iterable<AbstractContentSystemElementTypeLoader> $loaders
     */
    public function __construct(
        private readonly iterable $loaders,
    ) {
    }

    public function getDecorated(): AbstractContentSystemElementTypeRegistry
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<string, ContentSystemElementTypeSpecification>
     */
    public function all(): array
    {
        $types = [];

        // Cross-loader dedup: individual loaders guarantee internal uniqueness, this catches collisions across loaders
        foreach ($this->loaders as $loader) {
            foreach ($loader->load() as $specification) {
                $name = $specification->name();

                if (\array_key_exists($name, $types)) {
                    throw ContentSystemException::elementTypeDuplicate(
                        $name,
                        $types[$name]->source(),
                        $specification->source(),
                    );
                }

                $types[$name] = $specification;
            }
        }

        return $types;
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->all());
    }

    public function get(string $name): ContentSystemElementTypeSpecification
    {
        return $this->all()[$name] ?? throw ContentSystemException::elementTypeNotFound($name);
    }
}
