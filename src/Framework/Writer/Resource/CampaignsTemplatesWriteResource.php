<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CampaignsTemplatesWriteResource extends WriteResource
{
    protected const PATH_FIELD = 'path';
    protected const DESCRIPTION_FIELD = 'description';

    public function __construct()
    {
        parent::__construct('s_campaigns_templates');

        $this->fields[self::PATH_FIELD] = (new StringField('path'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CampaignsTemplatesWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\CampaignsTemplatesWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CampaignsTemplatesWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CampaignsTemplatesWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CampaignsTemplatesWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
