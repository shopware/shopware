<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DataAbstractionLayer\Definition;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpSigningKeyCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class UcpSigningKeyDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ucp_signing_key';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UcpSigningKeyEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UcpSigningKeyCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new PrimaryKey(), new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))
                ->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('kid', 'kid'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new StringField('algorithm', 'algorithm'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            (new JsonField('public_jwk', 'publicJwk'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            // private_key_pem_encrypted is encrypted at rest and MUST NEVER be ApiAware.
            // Internal services (UcpSigningKeyProvider) write via system-scoped context.
            (new BlobField('private_key_pem_encrypted', 'privateKeyPemEncrypted'))
                ->addFlags(new Required()),
            (new StringField('status', 'status'))->addFlags(new ApiAware(AdminApiSource::class), new Required()),
            new DateTimeField('activated_at', 'activatedAt'),
            new DateTimeField('retiring_at', 'retiringAt'),
            new DateTimeField('created_at', 'createdAt'),
            new DateTimeField('updated_at', 'updatedAt'),

            (new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false))
                ->addFlags(new ApiAware(AdminApiSource::class)),
        ]);
    }
}
