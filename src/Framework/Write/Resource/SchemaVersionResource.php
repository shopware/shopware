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

class SchemaVersionResource extends Resource
{
    protected const VERSION_FIELD = 'version';
    protected const START_DATE_FIELD = 'startDate';
    protected const COMPLETE_DATE_FIELD = 'completeDate';
    protected const NAME_FIELD = 'name';
    protected const ERROR_MSG_FIELD = 'errorMsg';

    public function __construct()
    {
        parent::__construct('schema_version');
        
        $this->primaryKeyFields[self::VERSION_FIELD] = (new StringField('version'))->setFlags(new Required());
        $this->fields[self::START_DATE_FIELD] = (new DateField('start_date'))->setFlags(new Required());
        $this->fields[self::COMPLETE_DATE_FIELD] = new DateField('complete_date');
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::ERROR_MSG_FIELD] = new StringField('error_msg');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\SchemaVersionResource::class
        ];
    }
}
