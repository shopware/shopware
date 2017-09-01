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

class EmarketingTellafriendResource extends Resource
{
    protected const DATUM_FIELD = 'datum';
    protected const RECIPIENT_FIELD = 'recipient';
    protected const SENDER_FIELD = 'sender';
    protected const CONFIRMED_FIELD = 'confirmed';

    public function __construct()
    {
        parent::__construct('s_emarketing_tellafriend');
        
        $this->fields[self::DATUM_FIELD] = new DateField('datum');
        $this->fields[self::RECIPIENT_FIELD] = (new StringField('recipient'))->setFlags(new Required());
        $this->fields[self::SENDER_FIELD] = new IntField('sender');
        $this->fields[self::CONFIRMED_FIELD] = new IntField('confirmed');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\EmarketingTellafriendResource::class
        ];
    }
}
