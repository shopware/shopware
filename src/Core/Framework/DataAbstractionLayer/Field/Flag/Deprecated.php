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
}
