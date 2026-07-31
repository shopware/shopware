<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Aggregate\AppDocumentType;

use Shopware\Core\Framework\App\Aggregate\AppDocumentTypeTranslation\AppDocumentTypeTranslationDefinition;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppDocumentTypeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_document_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppDocumentTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppDocumentTypeEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.13.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of apps document type.'),
            (new StringField('technical_name', 'technicalName'))->addFlags(new Required())->setDescription('It is a unique identity of an AppDocumentType.'),
            (new JsonField('config', 'config'))->setDescription('Specifies detailed information about the document type.'),
            (new JsonField('formats', 'formats'))->setDescription('Output formats this document type supports.'),
            new TranslatedField('label'),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new CascadeDelete(), new Required())->setDescription('Unique identity of app.'),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
            (new TranslationsAssociationField(AppDocumentTypeTranslationDefinition::class, 'app_document_type_id'))->addFlags(new Required()),
        ]);
    }
}
