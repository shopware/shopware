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

class MultiEditFilterResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_multi_edit_filter');
        
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['filterString'] = (new LongTextField('filter_string'))->setFlags(new Required());
        $this->fields['description'] = (new LongTextField('description'))->setFlags(new Required());
        $this->fields['created'] = new DateField('created');
        $this->fields['isFavorite'] = new BoolField('is_favorite');
        $this->fields['isSimple'] = new BoolField('is_simple');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\MultiEditFilterResource::class
        ];
    }
}
