<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use function Flag\next6010;

class CustomerGroupTranslationDefinition extends EntityTranslationDefinition
{
    public const ENTITY_NAME = 'customer_group_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomerGroupTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomerGroupTranslationEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return CustomerGroupDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            new CustomFields(),
        ]);

        if (next6010()) {
            $collection->add(new StringField('registration_title', 'registrationTitle'));
            $collection->add((new LongTextField('registration_introduction', 'registrationIntroduction'))->addFlags(new AllowHtml()));
            $collection->add(new BoolField('registration_only_company_registration', 'registrationOnlyCompanyRegistration'));
            $collection->add(new LongTextField('registration_seo_meta_description', 'registrationSeoMetaDescription'));
        }

        return $collection;
    }
}
