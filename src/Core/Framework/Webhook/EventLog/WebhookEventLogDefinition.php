<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\EventLog;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class WebhookEventLogDefinition extends EntityDefinition
{
    final public const STATUS_QUEUED = 'queued';

    final public const STATUS_RUNNING = 'running';

    final public const STATUS_FAILED = 'failed';

    final public const STATUS_SUCCESS = 'success';

    final public const ENTITY_NAME = 'webhook_event_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return WebhookEventLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return WebhookEventLogCollection::class;
    }

    public function since(): ?string
    {
        return '6.4.1.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('app_name', 'appName'),
            (new StringField('webhook_name', 'webhookName'))->addFlags(new Required()),
            (new StringField('event_name', 'eventName'))->addFlags(new Required()),
            (new StringField('delivery_status', 'deliveryStatus'))->addFlags(new Required()),
            new IntField('timestamp', 'timestamp'),
            new IntField('processing_time', 'processingTime'),
            new StringField('app_version', 'appVersion'),
            new JsonField('request_content', 'requestContent'),
            new JsonField('response_content', 'responseContent'),
            new IntField('response_status_code', 'responseStatusCode'),
            new StringField('response_reason_phrase', 'responseReasonPhrase'),
            (new StringField('url', 'url', 500))->addFlags(new Required()),
            (new BlobField('serialized_webhook_message', 'serializedWebhookMessage'))->removeFlag(ApiAware::class)->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            new CustomFields(),
        ]);
    }
}
