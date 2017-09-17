<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ConfigFormFieldResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const VALUE_FIELD = 'value';
    protected const LABEL_FIELD = 'label';
    protected const DESCRIPTION_FIELD = 'description';
    protected const TYPE_FIELD = 'type';
    protected const REQUIRED_FIELD = 'required';
    protected const POSITION_FIELD = 'position';
    protected const SCOPE_FIELD = 'scope';

    public function __construct()
    {
        parent::__construct('config_form_field');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::VALUE_FIELD] = new LongTextField('value');
        $this->fields[self::LABEL_FIELD] = (new StringField('label'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields[self::TYPE_FIELD] = (new StringField('type'))->setFlags(new Required());
        $this->fields[self::REQUIRED_FIELD] = new BoolField('required');
        $this->fields[self::POSITION_FIELD] = new IntField('position');
        $this->fields[self::SCOPE_FIELD] = new IntField('scope');
        $this->fields['configForm'] = new ReferenceField('configFormUuid', 'uuid', \Shopware\Framework\Write\Resource\ConfigFormResource::class);
        $this->fields['configFormUuid'] = (new FkField('config_form_uuid', \Shopware\Framework\Write\Resource\ConfigFormResource::class, 'uuid'));
        $this->fields[self::LABEL_FIELD] = new TranslatedField('label', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields[self::DESCRIPTION_FIELD] = new TranslatedField('description', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['translations'] = new SubresourceField(\Shopware\Framework\Write\Resource\ConfigFormFieldTranslationResource::class, 'languageUuid');
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\ConfigFormResource::class,
            \Shopware\Framework\Write\Resource\ConfigFormFieldResource::class,
            \Shopware\Framework\Write\Resource\ConfigFormFieldTranslationResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Framework\Event\ConfigFormFieldWrittenEvent
    {
        $event = new \Shopware\Framework\Event\ConfigFormFieldWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\ConfigFormResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ConfigFormResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\ConfigFormFieldResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ConfigFormFieldResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\ConfigFormFieldTranslationResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ConfigFormFieldTranslationResource::createWrittenEvent($updates));
        }

        return $event;
    }
}
