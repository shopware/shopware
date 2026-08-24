<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;

/**
 * The declared config contract of one data loader: the in-memory specification of its config keys.
 * The wire schema is generated elsewhere.
 */
#[Package('framework')]
final readonly class LoaderConfigSpecification
{
    /**
     * @param list<ConfigKeySpecification> $keys
     */
    public function __construct(public array $keys)
    {
    }

    /**
     * @return list<string>
     */
    public function requiredKeys(): array
    {
        $required = array_filter(
            $this->keys,
            static fn (ConfigKeySpecification $key): bool => $key->required
        );

        return array_values(array_map(
            static fn (ConfigKeySpecification $key): string => $key->name,
            $required
        ));
    }

    /**
     * @return list<ConfigKeySpecification>
     */
    public function keysOfKind(ConfigKeyKind $kind): array
    {
        return array_values(array_filter(
            $this->keys,
            static fn (ConfigKeySpecification $key): bool => $key->kind === $kind
        ));
    }
}
