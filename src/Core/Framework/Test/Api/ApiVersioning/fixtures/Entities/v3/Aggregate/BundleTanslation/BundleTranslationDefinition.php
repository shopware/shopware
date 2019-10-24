<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\Aggregate\BundleTanslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3\BundleDefinition;

class BundleTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return '_test_bundle_translation';
    }

    public function getParentDefinitionClass(): string
    {
        return BundleDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new LongTextField('translated_description', 'translatedDescription'),
        ]);
    }
}
