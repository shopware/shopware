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

class CampaignsHtmlResource extends Resource
{
    protected const PARENTID_FIELD = 'parentID';
    protected const HEADLINE_FIELD = 'headline';
    protected const HTML_FIELD = 'html';
    protected const IMAGE_FIELD = 'image';
    protected const LINK_FIELD = 'link';
    protected const ALIGNMENT_FIELD = 'alignment';

    public function __construct()
    {
        parent::__construct('s_campaigns_html');
        
        $this->fields[self::PARENTID_FIELD] = new IntField('parentID');
        $this->fields[self::HEADLINE_FIELD] = (new StringField('headline'))->setFlags(new Required());
        $this->fields[self::HTML_FIELD] = (new LongTextField('html'))->setFlags(new Required());
        $this->fields[self::IMAGE_FIELD] = (new StringField('image'))->setFlags(new Required());
        $this->fields[self::LINK_FIELD] = (new StringField('link'))->setFlags(new Required());
        $this->fields[self::ALIGNMENT_FIELD] = (new StringField('alignment'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CampaignsHtmlResource::class
        ];
    }
}
