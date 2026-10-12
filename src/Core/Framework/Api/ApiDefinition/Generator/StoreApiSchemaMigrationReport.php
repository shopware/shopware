<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class StoreApiSchemaMigrationReport implements \JsonSerializable
{
    /**
     * @param list<string> $jsonOverridesPhpGenerated
     * @param list<string> $phpGeneratedOnly
     * @param list<string> $jsonWithoutPhpGenerated
     */
    public function __construct(
        public readonly array $jsonOverridesPhpGenerated,
        public readonly array $phpGeneratedOnly,
        public readonly array $jsonWithoutPhpGenerated,
    ) {
    }

    public function hasMismatches(): bool
    {
        return $this->jsonOverridesPhpGenerated !== []
            || $this->phpGeneratedOnly !== [];
    }

    /**
     * @return array{
     *     jsonOverridesPhpGenerated: list<string>,
     *     phpGeneratedOnly: list<string>,
     *     jsonWithoutPhpGenerated: list<string>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'jsonOverridesPhpGenerated' => $this->jsonOverridesPhpGenerated,
            'phpGeneratedOnly' => $this->phpGeneratedOnly,
            'jsonWithoutPhpGenerated' => $this->jsonWithoutPhpGenerated,
        ];
    }
}
