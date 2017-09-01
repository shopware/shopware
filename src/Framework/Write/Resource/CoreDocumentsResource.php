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

class CoreDocumentsResource extends Resource
{
    protected const NAME_FIELD = 'name';
    protected const TEMPLATE_FIELD = 'template';
    protected const NUMBERS_FIELD = 'numbers';
    protected const LEFT_FIELD = 'left';
    protected const RIGHT_FIELD = 'right';
    protected const TOP_FIELD = 'top';
    protected const BOTTOM_FIELD = 'bottom';
    protected const PAGEBREAK_FIELD = 'pagebreak';

    public function __construct()
    {
        parent::__construct('s_core_documents');
        
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_FIELD] = (new StringField('template'))->setFlags(new Required());
        $this->fields[self::NUMBERS_FIELD] = (new StringField('numbers'))->setFlags(new Required());
        $this->fields[self::LEFT_FIELD] = (new IntField('left'))->setFlags(new Required());
        $this->fields[self::RIGHT_FIELD] = (new IntField('right'))->setFlags(new Required());
        $this->fields[self::TOP_FIELD] = (new IntField('top'))->setFlags(new Required());
        $this->fields[self::BOTTOM_FIELD] = (new IntField('bottom'))->setFlags(new Required());
        $this->fields[self::PAGEBREAK_FIELD] = (new IntField('pagebreak'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreDocumentsResource::class
        ];
    }
}
