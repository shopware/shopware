<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @package business-ops
 */
interface MediaUploadedAware extends FlowEventAware
{
    public const MEDIA_ID = 'mediaId';

    public const MEDIA_UPLOADED = 'mediaUploaded';

    public function getMediaId(): string;
}
