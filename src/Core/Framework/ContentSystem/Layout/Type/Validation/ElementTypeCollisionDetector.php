<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Validation;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class ElementTypeCollisionDetector
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
    ) {
    }

    /**
     * Simulates whether proposed type names can be integrated into
     * the current registry state without collisions.
     *
     * @param array<string, string> $proposed name => source label
     * @param array<string, string> $additionalRegistered name => source label
     *                                                    (entries not in all() but treated as occupied, e.g. inactive app types)
     */
    public function validate(
        array $proposed,
        ?string $excludeSource,
        array $additionalRegistered,
    ): void {
        $existing = $this->registry->all();

        foreach ($proposed as $name => $source) {
            if (\array_key_exists($name, $existing)
                && ($excludeSource === null || $existing[$name]->source() !== $excludeSource)
            ) {
                throw ContentSystemException::elementTypeDuplicate(
                    $name,
                    $existing[$name]->source(),
                    $source
                );
            }

            if (\array_key_exists($name, $additionalRegistered)) {
                throw ContentSystemException::elementTypeDuplicate(
                    $name,
                    $additionalRegistered[$name],
                    $source
                );
            }
        }
    }
}
