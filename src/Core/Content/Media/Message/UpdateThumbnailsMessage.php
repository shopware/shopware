<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class UpdateThumbnailsMessage extends GenerateThumbnailsMessage
{
    private bool $strict = false;

    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed. Use setStrict() instead.
     */
    public function setIsStrict(bool $isStrict): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0', 'setStrict'),
        );

        $this->setStrict($isStrict);
    }

    public function setStrict(bool $isStrict): void
    {
        $this->strict = $isStrict;
    }
}
