<?php declare(strict_types=1);

namespace Shopware\Framework\Writer\Resource;

use Shopware\Api\Write\Field\BoolField;
use Shopware\Api\Write\Field\DateField;
use Shopware\Api\Write\Field\LongTextField;
use Shopware\Api\Write\Field\LongTextWithHtmlField;
use Shopware\Api\Write\Field\StringField;
use Shopware\Api\Write\Field\SubresourceField;
use Shopware\Api\Write\Field\UuidField;
use Shopware\Api\Write\Flag\Required;
use Shopware\Api\Write\WriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\PluginWrittenEvent;
use Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource;
use Shopware\ShopTemplate\Writer\Resource\ShopTemplateWriteResource;

class PluginWriteResource extends WriteResource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const LABEL_FIELD = 'label';
    protected const DESCRIPTION_FIELD = 'description';
    protected const DESCRIPTION_LONG_FIELD = 'descriptionLong';
    protected const ACTIVE_FIELD = 'active';
    protected const INSTALLATION_DATE_FIELD = 'installationDate';
    protected const UPDATE_DATE_FIELD = 'updateDate';
    protected const REFRESH_DATE_FIELD = 'refreshDate';
    protected const AUTHOR_FIELD = 'author';
    protected const COPYRIGHT_FIELD = 'copyright';
    protected const LICENSE_FIELD = 'license';
    protected const VERSION_FIELD = 'version';
    protected const SUPPORT_FIELD = 'support';
    protected const CHANGES_FIELD = 'changes';
    protected const LINK_FIELD = 'link';
    protected const STORE_VERSION_FIELD = 'storeVersion';
    protected const STORE_DATE_FIELD = 'storeDate';
    protected const CAPABILITY_UPDATE_FIELD = 'capabilityUpdate';
    protected const CAPABILITY_INSTALL_FIELD = 'capabilityInstall';
    protected const CAPABILITY_ENABLE_FIELD = 'capabilityEnable';
    protected const UPDATE_SOURCE_FIELD = 'updateSource';
    protected const UPDATE_VERSION_FIELD = 'updateVersion';
    protected const CAPABILITY_SECURE_UNINSTALL_FIELD = 'capabilitySecureUninstall';

    public function __construct()
    {
        parent::__construct('plugin');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->primaryKeyFields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::LABEL_FIELD] = (new StringField('label'))->setFlags(new Required());
        $this->fields[self::DESCRIPTION_FIELD] = new LongTextField('description');
        $this->fields[self::DESCRIPTION_LONG_FIELD] = new LongTextWithHtmlField('description_long');
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::INSTALLATION_DATE_FIELD] = new DateField('installation_date');
        $this->fields[self::UPDATE_DATE_FIELD] = new DateField('update_date');
        $this->fields[self::REFRESH_DATE_FIELD] = new DateField('refresh_date');
        $this->fields[self::AUTHOR_FIELD] = new StringField('author');
        $this->fields[self::COPYRIGHT_FIELD] = new StringField('copyright');
        $this->fields[self::LICENSE_FIELD] = new StringField('license');
        $this->fields[self::VERSION_FIELD] = (new StringField('version'))->setFlags(new Required());
        $this->fields[self::SUPPORT_FIELD] = new StringField('support');
        $this->fields[self::CHANGES_FIELD] = new LongTextField('changes');
        $this->fields[self::LINK_FIELD] = new StringField('link');
        $this->fields[self::STORE_VERSION_FIELD] = new StringField('store_version');
        $this->fields[self::STORE_DATE_FIELD] = new DateField('store_date');
        $this->fields[self::CAPABILITY_UPDATE_FIELD] = (new BoolField('capability_update'))->setFlags(new Required());
        $this->fields[self::CAPABILITY_INSTALL_FIELD] = (new BoolField('capability_install'))->setFlags(new Required());
        $this->fields[self::CAPABILITY_ENABLE_FIELD] = (new BoolField('capability_enable'))->setFlags(new Required());
        $this->fields[self::UPDATE_SOURCE_FIELD] = new StringField('update_source');
        $this->fields[self::UPDATE_VERSION_FIELD] = new StringField('update_version');
        $this->fields[self::CAPABILITY_SECURE_UNINSTALL_FIELD] = (new BoolField('capability_secure_uninstall'))->setFlags(new Required());
        $this->fields['configForms'] = new SubresourceField(ConfigFormWriteResource::class);
        $this->fields['paymentMethods'] = new SubresourceField(PaymentMethodWriteResource::class);
        $this->fields['shopTemplates'] = new SubresourceField(ShopTemplateWriteResource::class);
        $this->fields['shoppingWorldComponents'] = new SubresourceField(ShoppingWorldComponentWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            ConfigFormWriteResource::class,
            PaymentMethodWriteResource::class,
            self::class,
            ShopTemplateWriteResource::class,
            ShoppingWorldComponentWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): PluginWrittenEvent
    {
        $uuids = [];
        if (isset($updates[self::class])) {
            $uuids = array_column($updates[self::class], 'uuid');
        }

        $event = new PluginWrittenEvent($uuids, $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            if (!array_key_exists($class, $updates) || count($updates[$class]) === 0) {
                continue;
            }

            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}
