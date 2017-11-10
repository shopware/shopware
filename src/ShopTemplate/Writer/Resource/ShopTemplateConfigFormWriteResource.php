<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ShopTemplate\Event\ShopTemplateConfigFormWrittenEvent;

class ShopTemplateConfigFormWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const TYPE_FIELD = 'type';
    protected const NAME_FIELD = 'name';
    protected const TITLE_FIELD = 'title';
    protected const ATTRIBUTES_FIELD = 'attributes';

    public function __construct()
    {
        parent::__construct('shop_template_config_form');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TITLE_FIELD] = new StringField('title');
        $this->fields[self::ATTRIBUTES_FIELD] = new LongTextField('attributes');
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', self::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', self::class, 'uuid'));
        $this->fields['shopTemplate'] = new ReferenceField('shopTemplateUuid', 'uuid', ShopTemplateWriteResource::class);
        $this->fields['shopTemplateUuid'] = (new FkField('shop_template_uuid', ShopTemplateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['parent'] = new SubresourceField(self::class);
        $this->fields['fields'] = new SubresourceField(ShopTemplateConfigFormFieldWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
            ShopTemplateWriteResource::class,
            ShopTemplateConfigFormFieldWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopTemplateConfigFormWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ShopTemplateConfigFormWrittenEvent($uuids, $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
