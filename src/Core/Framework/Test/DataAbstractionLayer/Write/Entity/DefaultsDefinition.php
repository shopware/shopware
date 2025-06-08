<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class DefaultsDefinition extends EntityDefinition
{
    final public const SCHEMA = 'CREATE TABLE IF NOT EXISTS `defaults` (
        `id` BINARY(16) NOT NULL PRIMARY KEY,
        `active` int(1) NOT NULL,
        `created_at` DATETIME NOT NULL
    )';

    public function getEntityName(): string
    {
        return 'defaults';
    }

    public function getDefaults(): array
    {
        return [
            'active' => true,
            'children' => [
                [
                    'foo' => 'Default foo',
                    'name' => 'Default name',
                ],
            ],
        ];
    }

    public function since(): ?string
    {
        return '6.4.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new BoolField('active', 'active'))->addFlags(new Required()),
            new OneToManyAssociationField('children', DefaultsChildDefinition::class, 'defaults_id'),
        ]);
    }
}
