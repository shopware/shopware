<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter;

use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class MailHeaderFooterDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'mail_header_footer';

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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            new BoolField('system_default', 'systemDefault'),

            // translatable fields
            new TranslatedField('name'),
            new TranslatedField('description'),
            new TranslatedField('headerHtml'),
            new TranslatedField('headerPlain'),
            new TranslatedField('footerHtml'),
            new TranslatedField('footerPlain'),

            (new TranslationsAssociationField(MailHeaderFooterTranslationDefinition::class, 'mail_header_footer_id'))->addFlags(new Required()),
            new OneToManyAssociationField('salesChannels', SalesChannelDefinition::class, 'mail_header_footer_id'),
        ]);
    }
}
