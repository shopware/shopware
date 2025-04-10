<?php
declare(strict_types=1);

/*
 * Created by netlogix GmbH & Co. KG
 *
 * @copyright netlogix GmbH & Co. KG
 */

namespace Shopware\Core\Content\Media\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class ResolveRemoteThumbnailUrlExtension extends Extension
{
    public const NAME = 'remote_thumbnail_url.resolve';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        public string $mediaUrl,
        public string $mediaPath,
        public string $width,
        public string $height,
        public string $pattern,
        public ?\DateTimeInterface $mediaUpdatedAt
    ) {
    }
}
