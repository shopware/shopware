<?php declare(strict_types=1);

namespace Shopware\Framework\Api2\Resource;

use Shopware\Framework\Api2\ApiFlag\Required;
use Shopware\Framework\Api2\Field\FkField;
use Shopware\Framework\Api2\Field\IntField;
use Shopware\Framework\Api2\Field\ReferenceField;
use Shopware\Framework\Api2\Field\StringField;
use Shopware\Framework\Api2\Field\BoolField;
use Shopware\Framework\Api2\Field\DateField;
use Shopware\Framework\Api2\Field\SubresourceField;
use Shopware\Framework\Api2\Field\LongTextField;
use Shopware\Framework\Api2\Field\LongTextWithHtmlField;
use Shopware\Framework\Api2\Field\FloatField;
use Shopware\Framework\Api2\Field\TranslatedField;
use Shopware\Framework\Api2\Field\UuidField;
use Shopware\Framework\Api2\Resource\ApiResource;

class CoreRulesetsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_rulesets');
        
        $this->fields['paymentID'] = (new IntField('paymentID'))->setFlags(new Required());
        $this->fields['rule1'] = (new StringField('rule1'))->setFlags(new Required());
        $this->fields['value1'] = (new StringField('value1'))->setFlags(new Required());
        $this->fields['rule2'] = (new StringField('rule2'))->setFlags(new Required());
        $this->fields['value2'] = (new StringField('value2'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreRulesetsResource::class
        ];
    }
}
