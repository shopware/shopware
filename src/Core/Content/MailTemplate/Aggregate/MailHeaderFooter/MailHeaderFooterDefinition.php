<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter;

use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

#[Package('sales-channel')]
class MailHeaderFooterDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'mail_header_footer';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return MailHeaderFooterEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new BoolField('system_default', 'systemDefault'))->addFlags(new ApiAware()),

            // translatable fields
            (new TranslatedField('name'))->addFlags(new ApiAware()),
            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new TranslatedField('headerHtml'))->addFlags(new ApiAware()),
            (new TranslatedField('headerPlain'))->addFlags(new ApiAware()),
            (new TranslatedField('footerHtml'))->addFlags(new ApiAware()),
            (new TranslatedField('footerPlain'))->addFlags(new ApiAware()),

            (new TranslationsAssociationField(MailHeaderFooterTranslationDefinition::class, 'mail_header_footer_id'))->addFlags(new ApiAware(), new Required()),
            new OneToManyAssociationField('salesChannels', SalesChannelDefinition::class, 'mail_header_footer_id'),
        ]);
    }
}
