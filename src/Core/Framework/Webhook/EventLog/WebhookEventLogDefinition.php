<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\EventLog;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
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

#[Package('framework')]
class WebhookEventLogDefinition extends EntityDefinition
{
    final public const STATUS_QUEUED = 'queued';

    final public const STATUS_RUNNING = 'running';

    final public const STATUS_PENDING_RETRY = 'pending_retry';

    final public const STATUS_FAILED = 'failed';

    final public const STATUS_SUCCESS = 'success';

    /**
     * Health-held delivery: a row written for a DEGRADED webhook, held for the cooldown probe.
     * Not in the transport's claimable-status set, so the receiver ignores it like SUCCESS/FAILED.
     * A SUSPENDED/DISABLED webhook writes no row at all — the dispatch gate skips it (#16565).
     */
    final public const STATUS_PAUSED = 'paused';

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

    public function getDefaults(): array
    {
        return [
            'onlyLiveVersion' => false,
        ];
    }

    public function since(): ?string
    {
        return '6.4.1.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of webhook event log.'),
            (new StringField('app_name', 'appName'))->setDescription('Unique name of app.'),
            (new StringField('webhook_name', 'webhookName'))->addFlags(new Required())->setDescription('Unique name of webhook.'),
            (new StringField('event_name', 'eventName'))->addFlags(new Required())->setDescription('Unique name of event.'),
            (new StringField('delivery_status', 'deliveryStatus'))->addFlags(new Required())->setDescription('Parameter that records \\\"success or failed\\\" status of the event.'),
            (new IntField('timestamp', 'timestamp'))->setDescription('Time at which the event occurred.'),
            (new IntField('processing_time', 'processingTime'))->setDescription('Time the event took to process.'),
            (new StringField('app_version', 'appVersion'))->setDescription('Version of teh app.'),
            (new JsonField('request_content', 'requestContent'))->setDescription('Represents the content sent as part of the Request.'),
            (new JsonField('response_content', 'responseContent'))->setDescription('Represents the content sent as part of the Response.'),
            (new IntField('response_status_code', 'responseStatusCode'))->setDescription('HTTP status codes that are typically generated to provide informational (1xx), successful (2xx), redirection (3xx), client error (4xx), or server error (5xx) responses.'),
            (new StringField('response_reason_phrase', 'responseReasonPhrase'))->setDescription('Parameter that stores the reason phrase or message associated with the response received from a webhook event.'),
            (new StringField('url', 'url', 500))->addFlags(new Required())->setDescription('A URL for the webhook event log.'),
            new BoolField('only_live_version', 'onlyLiveVersion'),
            (new BlobField('serialized_webhook_message', 'serializedWebhookMessage'))->removeFlag(ApiAware::class)->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new IntField('sequence', 'sequence'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new StringField('failure_reason', 'failureReason', 32))->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Reason a pending webhook delivery was dropped by the endpoint-health model — e.g. endpoint_suspended (→ SUSPENDED) or webhook_disabled (→ DISABLED); null otherwise (#16565).'),
        ]);
    }
}
