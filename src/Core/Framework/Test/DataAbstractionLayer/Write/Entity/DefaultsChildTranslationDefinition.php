<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class DefaultsChildTranslationDefinition extends EntityTranslationDefinition
{
    final public const SCHEMA = '
        CREATE TABLE IF NOT EXISTS `defaults_child_translation` (
            `defaults_child_id` BINARY(16) NOT NULL,
            `language_id` BINARY(16) NOT NULL,
            `name` text not null,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`defaults_child_id`, `language_id`)
        )';

    public function getEntityName(): string
    {
        return 'defaults_child_translation';
    }

    public function since(): ?string
    {
        return '6.4.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return DefaultsChildDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
        ]);
    }
}
