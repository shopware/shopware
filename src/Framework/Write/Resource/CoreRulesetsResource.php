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

class CoreRulesetsResource extends Resource
{
    protected const PAYMENTID_FIELD = 'paymentID';
    protected const RULE1_FIELD = 'rule1';
    protected const VALUE1_FIELD = 'value1';
    protected const RULE2_FIELD = 'rule2';
    protected const VALUE2_FIELD = 'value2';

    public function __construct()
    {
        parent::__construct('s_core_rulesets');
        
        $this->fields[self::PAYMENTID_FIELD] = (new IntField('paymentID'))->setFlags(new Required());
        $this->fields[self::RULE1_FIELD] = (new StringField('rule1'))->setFlags(new Required());
        $this->fields[self::VALUE1_FIELD] = (new StringField('value1'))->setFlags(new Required());
        $this->fields[self::RULE2_FIELD] = (new StringField('rule2'))->setFlags(new Required());
        $this->fields[self::VALUE2_FIELD] = (new StringField('value2'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\CoreRulesetsResource::class
        ];
    }
}
