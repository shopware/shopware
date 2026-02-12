<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
final readonly class FlagMetadata
{
    /**
     * @param class-string<Flag> $flagClass
     * @param list<mixed> $args
     *
     * @throw DataAbstractionLayerException
     */
    public function __construct(
        public string $flagClass,
        public array $args = [],
    ) {
        if (!is_a($flagClass, Flag::class, true)) {
            throw DataAbstractionLayerException::invalidFlagMetadataClass($flagClass);
        }
    }

    public function createFlag(): Flag
    {
        return new $this->flagClass(...$this->args);
    }

    public function toDefinition(): Definition
    {
        return new Definition(self::class, [
            $this->flagClass,
            $this->args,
        ]);
    }
}
