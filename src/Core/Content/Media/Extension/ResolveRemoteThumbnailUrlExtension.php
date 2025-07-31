<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Media\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends Extension<string|null>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class ResolveRemoteThumbnailUrlExtension extends Extension
{
    public const NAME = 'remote_thumbnail_url.resolve';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        public string $mediaUrl,
        /**
         * @deprecated is resolved in the extension function
         */
        public string $mediaPath,
        public string $width,
        public string $height,
        public string $pattern,
        /**
         * @deprecated is resolved in the extension function
         */
        public ?\DateTimeInterface $mediaUpdatedAt,
        public Entity $mediaEntity,
    ) {
    }
}
