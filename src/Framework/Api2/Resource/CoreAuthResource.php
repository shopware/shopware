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

class CoreAuthResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_auth');
        
        $this->fields['roleID'] = (new IntField('roleID'))->setFlags(new Required());
        $this->fields['username'] = (new StringField('username'))->setFlags(new Required());
        $this->fields['password'] = (new StringField('password'))->setFlags(new Required());
        $this->fields['encoder'] = new StringField('encoder');
        $this->fields['apiKey'] = new StringField('apiKey');
        $this->fields['localeID'] = (new IntField('localeID'))->setFlags(new Required());
        $this->fields['sessionID'] = new StringField('sessionID');
        $this->fields['lastlogin'] = new DateField('lastlogin');
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['email'] = (new StringField('email'))->setFlags(new Required());
        $this->fields['active'] = new BoolField('active');
        $this->fields['failedlogins'] = (new IntField('failedlogins'))->setFlags(new Required());
        $this->fields['lockeduntil'] = new DateField('lockeduntil');
        $this->fields['extendedEditor'] = new BoolField('extended_editor');
        $this->fields['disabledCache'] = new BoolField('disabled_cache');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CoreAuthResource::class
        ];
    }
}
