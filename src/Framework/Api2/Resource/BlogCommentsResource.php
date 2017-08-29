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

class BlogCommentsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_blog_comments');
        
        $this->fields['blogId'] = new IntField('blog_id');
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['headline'] = (new StringField('headline'))->setFlags(new Required());
        $this->fields['comment'] = (new LongTextField('comment'))->setFlags(new Required());
        $this->fields['creationDate'] = (new DateField('creation_date'))->setFlags(new Required());
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['email'] = (new StringField('email'))->setFlags(new Required());
        $this->fields['points'] = (new FloatField('points'))->setFlags(new Required());
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\BlogCommentsResource::class
        ];
    }
}
