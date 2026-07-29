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
     * @param list<string> $jsonOverridesPhpGeneratedAllowed
     * @param list<string> $jsonOverridesPhpGeneratedWithoutAllowlist
     * @param list<string> $phpGeneratedOnly
     * @param list<string> $phpGeneratedOnlyAllowed
     * @param list<string> $phpGeneratedOnlyWithoutAllowlist
     * @param list<string> $jsonWithoutPhpGenerated
     * @param list<string> $allowlistWithoutJsonOverridesPhpGeneratedSchema
     * @param list<string> $allowlistWithoutPhpGeneratedOnlySchema
     * @param list<string> $allowlistWithoutPhpGeneratedSchema
     */
    public function __construct(
        public readonly array $jsonOverridesPhpGenerated,
        public readonly array $jsonOverridesPhpGeneratedAllowed,
        public readonly array $jsonOverridesPhpGeneratedWithoutAllowlist,
        public readonly array $phpGeneratedOnly,
        public readonly array $phpGeneratedOnlyAllowed,
        public readonly array $phpGeneratedOnlyWithoutAllowlist,
        public readonly array $jsonWithoutPhpGenerated,
        public readonly array $allowlistWithoutJsonOverridesPhpGeneratedSchema,
        public readonly array $allowlistWithoutPhpGeneratedOnlySchema,
        public readonly array $allowlistWithoutPhpGeneratedSchema,
    ) {
    }

    public function hasMismatches(): bool
    {
        return $this->jsonOverridesPhpGeneratedWithoutAllowlist !== []
            || $this->phpGeneratedOnlyWithoutAllowlist !== []
            || $this->allowlistWithoutJsonOverridesPhpGeneratedSchema !== []
            || $this->allowlistWithoutPhpGeneratedOnlySchema !== [];
    }

    /**
     * @return array{
     *     jsonOverridesPhpGenerated: list<string>,
     *     jsonOverridesPhpGeneratedAllowed: list<string>,
     *     jsonOverridesPhpGeneratedWithoutAllowlist: list<string>,
     *     phpGeneratedOnly: list<string>,
     *     phpGeneratedOnlyAllowed: list<string>,
     *     phpGeneratedOnlyWithoutAllowlist: list<string>,
     *     jsonWithoutPhpGenerated: list<string>,
     *     allowlistWithoutJsonOverridesPhpGeneratedSchema: list<string>,
     *     allowlistWithoutPhpGeneratedOnlySchema: list<string>,
     *     allowlistWithoutPhpGeneratedSchema: list<string>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'jsonOverridesPhpGenerated' => $this->jsonOverridesPhpGenerated,
            'jsonOverridesPhpGeneratedAllowed' => $this->jsonOverridesPhpGeneratedAllowed,
            'jsonOverridesPhpGeneratedWithoutAllowlist' => $this->jsonOverridesPhpGeneratedWithoutAllowlist,
            'phpGeneratedOnly' => $this->phpGeneratedOnly,
            'phpGeneratedOnlyAllowed' => $this->phpGeneratedOnlyAllowed,
            'phpGeneratedOnlyWithoutAllowlist' => $this->phpGeneratedOnlyWithoutAllowlist,
            'jsonWithoutPhpGenerated' => $this->jsonWithoutPhpGenerated,
            'allowlistWithoutJsonOverridesPhpGeneratedSchema' => $this->allowlistWithoutJsonOverridesPhpGeneratedSchema,
            'allowlistWithoutPhpGeneratedOnlySchema' => $this->allowlistWithoutPhpGeneratedOnlySchema,
            'allowlistWithoutPhpGeneratedSchema' => $this->allowlistWithoutPhpGeneratedSchema,
        ];
    }
}
