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

class CorePluginsResource extends ApiResource
{
    public function __construct()
    {
        parent::__construct('s_core_plugins');
        
        $this->fields['namespace'] = (new StringField('namespace'))->setFlags(new Required());
        $this->fields['name'] = (new StringField('name'))->setFlags(new Required());
        $this->fields['label'] = (new StringField('label'))->setFlags(new Required());
        $this->fields['source'] = (new StringField('source'))->setFlags(new Required());
        $this->fields['description'] = new LongTextField('description');
        $this->fields['descriptionLong'] = new LongTextWithHtmlField('description_long');
        $this->fields['active'] = (new BoolField('active'))->setFlags(new Required());
        $this->fields['added'] = (new DateField('added'))->setFlags(new Required());
        $this->fields['installationDate'] = new DateField('installation_date');
        $this->fields['updateDate'] = new DateField('update_date');
        $this->fields['refreshDate'] = new DateField('refresh_date');
        $this->fields['author'] = new StringField('author');
        $this->fields['copyright'] = new StringField('copyright');
        $this->fields['license'] = new StringField('license');
        $this->fields['version'] = (new StringField('version'))->setFlags(new Required());
        $this->fields['support'] = new StringField('support');
        $this->fields['changes'] = new LongTextField('changes');
        $this->fields['link'] = new StringField('link');
        $this->fields['storeVersion'] = new StringField('store_version');
        $this->fields['storeDate'] = new DateField('store_date');
        $this->fields['capabilityUpdate'] = (new IntField('capability_update'))->setFlags(new Required());
        $this->fields['capabilityInstall'] = (new IntField('capability_install'))->setFlags(new Required());
        $this->fields['capabilityEnable'] = (new IntField('capability_enable'))->setFlags(new Required());
        $this->fields['updateSource'] = new StringField('update_source');
        $this->fields['updateVersion'] = new StringField('update_version');
        $this->fields['capabilitySecureUninstall'] = new IntField('capability_secure_uninstall');
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Api2\Resource\CorePluginsResource::class
        ];
    }
}
