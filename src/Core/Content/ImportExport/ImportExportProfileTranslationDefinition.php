<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed
 */
#[Package('fundamentals@after-sales')]
class ImportExportProfileTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = ImportExportProfileDefinition::ENTITY_NAME . '_translation';

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function getEntityName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return self::ENTITY_NAME;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function getCollectionClass(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return ImportExportProfileTranslationCollection::class;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function getEntityClass(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return ImportExportProfileTranslationEntity::class;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function since(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return '6.2.0.0';
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    protected function getParentDefinitionClass(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return ImportExportProfileDefinition::class;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    protected function defineFields(): FieldCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return new FieldCollection([
            new StringField('label', 'label'),
        ]);
    }
}
