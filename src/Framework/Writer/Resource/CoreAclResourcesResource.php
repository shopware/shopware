<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CoreAclResourcesResource extends Resource
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
            \Shopware\Framework\Write\Resource\CoreAclResourcesResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\CoreAclResourcesWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CoreAclResourcesWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CoreAclResourcesResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CoreAclResourcesResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
