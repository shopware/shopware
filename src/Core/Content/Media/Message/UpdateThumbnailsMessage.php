<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class UpdateThumbnailsMessage extends GenerateThumbnailsMessage
{
    private bool $isStrict = false;

    public function isStrict(): bool
    {
        return $this->isStrict;
    }

    public function setIsStrict(bool $isStrict): void
    {
        $this->isStrict = $isStrict;
    }
}
