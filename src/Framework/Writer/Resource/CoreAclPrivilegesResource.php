<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class CoreAclPrivilegesResource extends Resource
{
    protected const RESOURCEID_FIELD = 'resourceID';
    protected const NAME_FIELD = 'name';

    public function __construct()
    {
        parent::__construct('s_core_acl_privileges');

        $this->fields[self::RESOURCEID_FIELD] = (new IntField('resourceID'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreAclPrivilegesResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\CoreAclPrivilegesWrittenEvent
    {
        $event = new \Shopware\Framework\Event\CoreAclPrivilegesWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\CoreAclPrivilegesResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\CoreAclPrivilegesResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
