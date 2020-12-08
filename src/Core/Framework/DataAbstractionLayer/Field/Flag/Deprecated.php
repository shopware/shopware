<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

class Deprecated extends Flag
{
    /**
     * @var string
     */
    private $deprecatedSince;

    /**
     * @var string
     */
    private $willBeRemovedIn;

    /**
     * @var string|null
     */
    private $replacedBy;

    public function __construct(string $deprecatedSince, string $willBeRemovedIn, ?string $replacedBy = null)
    {
        $this->deprecatedSince = $deprecatedSince;
        $this->willBeRemovedIn = $willBeRemovedIn;
        $this->replacedBy = $replacedBy;
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
