<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class CoreAclResourcesWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';
    protected const PLUGINID_FIELD = 'pluginID';

    public function __construct()
    {
        parent::__construct('s_core_acl_resources');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::PLUGINID_FIELD] = new IntField('pluginID');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreAclResourcesWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Framework\Event\CoreAclResourcesWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CoreAclResourcesWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CoreAclResourcesWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CoreAclResourcesWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
