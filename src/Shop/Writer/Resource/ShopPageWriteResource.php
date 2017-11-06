<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\IntField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Event\ShopPageWrittenEvent;

class ShopPageWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const VARIABLE_1_FIELD = 'variable1';
    protected const PATH_1_FIELD = 'path1';
    protected const VARIABLE_2_FIELD = 'variable2';
    protected const PATH_2_FIELD = 'path2';
    protected const VARIABLE_3_FIELD = 'variable3';
    protected const PATH_3_FIELD = 'path3';
    protected const DESCRIPTION_FIELD = 'description';
    protected const HTML_FIELD = 'html';
    protected const GROUPING_FIELD = 'grouping';
    protected const POSITION_FIELD = 'position';
    protected const LINK_FIELD = 'link';
    protected const LINK_TARGET_FIELD = 'linkTarget';
    protected const PARENT_ID_FIELD = 'parentId';
    protected const PAGE_TITLE_FIELD = 'pageTitle';
    protected const META_KEYWORDS_FIELD = 'metaKeywords';
    protected const META_DESCRIPTION_FIELD = 'metaDescription';
    protected const CHANGED_FIELD = 'changed';
    protected const SHOP_IDS_FIELD = 'shopIds';
    protected const SHOP_UUIDS_FIELD = 'shopUuids';

    public function __construct()
    {
        parent::__construct('shop_page');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::VARIABLE_1_FIELD] = (new StringField('variable_1'))->setFlags(new Required());
        $this->fields[self::PATH_1_FIELD] = (new StringField('path_1'))->setFlags(new Required());
        $this->fields[self::VARIABLE_2_FIELD] = (new StringField('variable_2'))->setFlags(new Required());
        $this->fields[self::PATH_2_FIELD] = (new StringField('path_2'))->setFlags(new Required());
        $this->fields[self::VARIABLE_3_FIELD] = (new StringField('variable_3'))->setFlags(new Required());
        $this->fields[self::PATH_3_FIELD] = (new StringField('path_3'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::HTML_FIELD] = (new LongTextField('html'))->setFlags(new Required());
        $this->fields[self::GROUPING_FIELD] = (new StringField('grouping'))->setFlags(new Required());
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::LINK_FIELD] = (new StringField('link'))->setFlags(new Required());
        $this->fields[self::LINK_TARGET_FIELD] = (new StringField('link_target'))->setFlags(new Required());
        $this->fields[self::PARENT_ID_FIELD] = new IntField('parent_id');
        $this->fields[self::PAGE_TITLE_FIELD] = (new StringField('page_title'))->setFlags(new Required());
        $this->fields[self::META_KEYWORDS_FIELD] = (new StringField('meta_keywords'))->setFlags(new Required());
        $this->fields[self::META_DESCRIPTION_FIELD] = (new StringField('meta_description'))->setFlags(new Required());
        $this->fields[self::CHANGED_FIELD] = new DateField('changed');
        $this->fields[self::SHOP_IDS_FIELD] = new StringField('shop_ids');
        $this->fields[self::SHOP_UUIDS_FIELD] = new LongTextField('shop_uuids');
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', self::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', self::class, 'uuid'));
        $this->fields['parent'] = new SubresourceField(self::class);
    }

    public function getWriteOrder(): array
    {
        return [
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopPageWrittenEvent
    {
        $uuids = [];
        if ($updates[self::class]) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new ShopPageWrittenEvent($uuids, $context, $rawData, $errors);

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
