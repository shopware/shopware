<?php declare(strict_types=1);

namespace Shopware\Category\Writer\Resource;

use Shopware\Api\Write\Field\FkField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\ReferenceField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Category\Event\CategoryTranslationWrittenEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Writer\Resource\ShopWriteResource;

class CategoryTranslationWriteResource extends WriteResource
{
    protected const NAME_FIELD = 'name';
    protected const PATH_NAMES_FIELD = 'pathNames';
    protected const META_KEYWORDS_FIELD = 'metaKeywords';
    protected const META_TITLE_FIELD = 'metaTitle';
    protected const META_DESCRIPTION_FIELD = 'metaDescription';
    protected const CMS_HEADLINE_FIELD = 'cmsHeadline';
    protected const CMS_DESCRIPTION_FIELD = 'cmsDescription';

    public function __construct()
    {
        parent::__construct('category_translation');

        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::PATH_NAMES_FIELD] = new LongTextField('path_names');
        $this->fields[self::META_KEYWORDS_FIELD] = new LongTextField('meta_keywords');
        $this->fields[self::META_TITLE_FIELD] = new StringField('meta_title');
        $this->fields[self::META_DESCRIPTION_FIELD] = new LongTextField('meta_description');
        $this->fields[self::CMS_HEADLINE_FIELD] = new StringField('cms_headline');
        $this->fields[self::CMS_DESCRIPTION_FIELD] = new LongTextField('cms_description');
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', CategoryWriteResource::class);
        $this->primaryKeyFields['categoryUuid'] = (new FkField('category_uuid', CategoryWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['language'] = new ReferenceField('languageUuid', 'uuid', ShopWriteResource::class);
        $this->primaryKeyFields['languageUuid'] = (new FkField('language_uuid', ShopWriteResource::class, 'uuid'))->setFlags(new Required());
    }

    public function getWriteOrder(): array
    {
        return [
            CategoryWriteResource::class,
            ShopWriteResource::class,
            self::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): CategoryTranslationWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new CategoryTranslationWrittenEvent($uuids, $context, $rawData, $errors);

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
