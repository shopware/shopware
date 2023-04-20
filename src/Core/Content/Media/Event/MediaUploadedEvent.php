<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Content\Flow\Dispatching\Aware\MediaUploadedAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - MediaUploadedAware is deprecated and will be removed in v6.6.0
 */
#[Package('content')]
class MediaUploadedEvent extends Event implements MediaUploadedAware, ScalarValuesAware, FlowEventAware, Hookable
{
    public const EVENT_NAME = 'media.uploaded';

    public function __construct(
        private readonly string $mediaId,
        private readonly Context $context
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('mediaId', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getValues(): array
    {
        return [
            FlowMailVariables::MEDIA_ID => $this->mediaId,
        ];
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getWebhookPayload(): array
    {
        return [
            'mediaId' => $this->mediaId,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $permissions->isAllowed('media', 'read');
    }
}
