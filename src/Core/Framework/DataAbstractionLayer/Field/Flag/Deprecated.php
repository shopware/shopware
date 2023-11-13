<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class Deprecated extends Flag
{
    public function __construct(
        private readonly string $deprecatedSince,
        private readonly string $willBeRemovedIn,
        private readonly ?string $replacedBy = null
    ) {
    }

    public function parse(): \Generator
    {
        yield 'deprecated' => [
            'deprecated_since' => $this->deprecatedSince,
            'will_be_removed_in' => $this->willBeRemovedIn,
            'replaced_by' => $this->replacedBy,
        ];
    }

    public function getReplaceBy(): ?string
    {
        return $this->replacedBy;
    }

    public function isRemovedInVersion(int $version): bool
    {
        $removedVersion = (int) str_replace('v', '', $this->willBeRemovedIn);
        if ($version >= $removedVersion) {
            return true;
        }

        return false;
    }

    public function isDeprecatedInVersion(int $version): bool
    {
        $deprecatedVersion = (int) str_replace('v', '', $this->deprecatedSince);

        if ($version >= $deprecatedVersion) {
            return true;
        }

        return false;
    }
}
