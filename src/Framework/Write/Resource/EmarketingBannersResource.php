<?php declare(strict_types=1);

namespace Shopware\Framework\Write\Resource;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class EmarketingBannersResource extends Resource
{
    protected const DESCRIPTION_FIELD = 'description';
    protected const VALID_FROM_FIELD = 'validFrom';
    protected const VALID_TO_FIELD = 'validTo';
    protected const IMG_FIELD = 'img';
    protected const LINK_FIELD = 'link';
    protected const LINK_TARGET_FIELD = 'linkTarget';
    protected const CATEGORYID_FIELD = 'categoryID';
    protected const EXTENSION_FIELD = 'extension';

    public function __construct()
    {
        parent::__construct('s_emarketing_banners');
        
        $this->fields[self::DESCRIPTION_FIELD] = (new StringField('description'))->setFlags(new Required());
        $this->fields[self::VALID_FROM_FIELD] = new DateField('valid_from');
        $this->fields[self::VALID_TO_FIELD] = new DateField('valid_to');
        $this->fields[self::IMG_FIELD] = (new StringField('img'))->setFlags(new Required());
        $this->fields[self::LINK_FIELD] = (new StringField('link'))->setFlags(new Required());
        $this->fields[self::LINK_TARGET_FIELD] = (new StringField('link_target'))->setFlags(new Required());
        $this->fields[self::CATEGORYID_FIELD] = new IntField('categoryID');
        $this->fields[self::EXTENSION_FIELD] = (new StringField('extension'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\EmarketingBannersResource::class
        ];
    }
}
