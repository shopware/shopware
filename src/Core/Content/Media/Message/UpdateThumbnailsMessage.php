<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

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

    public function setStrict(bool $isStrict): void
    {
        $this->strict = $isStrict;
    }
}
