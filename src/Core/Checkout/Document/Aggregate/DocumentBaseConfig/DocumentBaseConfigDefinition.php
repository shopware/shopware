<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;

#[Package('after-sales')]
class DocumentBaseConfigDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'document_base_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return DocumentBaseConfigCollection::class;
    }

    public function getEntityClass(): string
    {
        return DocumentBaseConfigEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'global' => false,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return DocumentTypeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the document base config.'),

            (new FkField('document_type_id', 'documentTypeId', DocumentTypeDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the document type.'),
            (new FkField('logo_id', 'logoId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of the company logo.'),

            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required())->setDescription('Name of the document.'),
            (new StringField('filename_prefix', 'filenamePrefix'))->addFlags(new ApiAware())->setDescription('A prefix name added to the file name separated by an underscore.'),
            (new StringField('filename_suffix', 'filenameSuffix'))->addFlags(new ApiAware())->setDescription('A suffix name added to the file name separated by an underscore.'),
            (new BoolField('global', 'global'))->addFlags(new ApiAware(), new Required())->setDescription('When set to `true`, the document can be used across all sales channels.'),
            (new NumberRangeField('document_number', 'documentNumber'))->addFlags(new ApiAware())->setDescription('Unique number associated with every document.'),
            (new StringField('page_size', 'pageSize', 32))->setDescription('The page size of the document.'),
            (new StringField('page_orientation', 'pageOrientation', 32))->setDescription('The page orientation of the document.'),
            (new IntField('items_per_page', 'itemsPerPage'))->setDescription('The number of items per page.'),
            (new BoolField('display_header', 'displayHeader'))->setDescription('Whether to display the header.'),
            (new BoolField('display_footer', 'displayFooter'))->setDescription('Whether to display the footer.'),
            (new BoolField('display_page_count', 'displayPageCount'))->setDescription('Whether to display the page count.'),
            (new BoolField('display_company_address', 'displayCompanyAddress'))->setDescription('Whether to display the company address.'),
            (new BoolField('display_return_address', 'displayReturnAddress'))->setDescription('Whether to display the return address.'),
            (new BoolField('display_customer_vat_id', 'displayCustomerVatId'))->setDescription('Whether to display the customer VAT ID.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new CreatedAtField())->addFlags(new ApiAware()),

            new ManyToOneAssociationField('documentType', 'document_type_id', DocumentTypeDefinition::class, 'id'),
            (new ManyToOneAssociationField('logo', 'logo_id', MediaDefinition::class, 'id'))->addFlags(new ApiAware())->setDescription('Logo in the document at the top-right corner.'),
            (new OneToManyAssociationField('salesChannels', DocumentBaseConfigSalesChannelDefinition::class, 'document_base_config_id', 'id'))->addFlags(new CascadeDelete()),

            (new JsonField('config', 'config'))->addFlags(new ApiAware(), new Deprecated('v6.7.11.0', 'v6.8.0.0', 'type'))->setDescription('Specifies detailed information about the component.'),
        ]);
    }
}
