<?php declare(strict_types=1);

namespace Shopware\Media\Writer\Resource;

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

class MediaAssociationResource extends Resource
{
    protected const MEDIAID_FIELD = 'mediaID';
    protected const TARGETTYPE_FIELD = 'targetType';
    protected const TARGETID_FIELD = 'targetID';

    public function __construct()
    {
        parent::__construct('s_media_association');
        
        $this->fields[self::MEDIAID_FIELD] = (new IntField('mediaID'))->setFlags(new Required());
        $this->fields[self::TARGETTYPE_FIELD] = (new StringField('targetType'))->setFlags(new Required());
        $this->fields[self::TARGETID_FIELD] = (new IntField('targetID'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Media\Writer\Resource\MediaAssociationResource::class
        ];
    }
}
