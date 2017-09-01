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

class StatisticSearchResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const CREATED_AT_FIELD = 'createdAt';
    protected const TERM_FIELD = 'term';
    protected const RESULT_COUNT_FIELD = 'resultCount';
    protected const SHOP_ID_FIELD = 'shopId';

    public function __construct()
    {
        parent::__construct('statistic_search');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::CREATED_AT_FIELD] = (new DateField('created_at'))->setFlags(new Required());
        $this->fields[self::TERM_FIELD] = (new StringField('term'))->setFlags(new Required());
        $this->fields[self::RESULT_COUNT_FIELD] = (new IntField('result_count'))->setFlags(new Required());
        $this->fields[self::SHOP_ID_FIELD] = new IntField('shop_id');
        $this->fields['shop'] = new ReferenceField('shopUuid', 'uuid', \Shopware\Shop\Gateway\Resource\ShopResource::class);
        $this->fields['shopUuid'] = (new FkField('shop_uuid', \Shopware\Shop\Gateway\Resource\ShopResource::class, 'uuid'));
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Shop\Gateway\Resource\ShopResource::class,
            \Shopware\Framework\Write\Resource\StatisticSearchResource::class
        ];
    }    
    
    public function getDefaults(string $type): array {
        if($type === self::FOR_UPDATE) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
            ];
        }

        if($type === self::FOR_INSERT) {
            return [
                self::UPDATED_AT_FIELD => new \DateTime(),
                self::CREATED_AT_FIELD => new \DateTime(),
            ];
        }

        throw new \InvalidArgumentException('Unable to generate default values, wrong type submitted');
    }
}
