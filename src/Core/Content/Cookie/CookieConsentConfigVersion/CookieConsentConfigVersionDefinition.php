<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\CookieConsentConfigVersion;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * Snapshot of the cookie banner configuration for a given configuration hash.
 *
 * Referenced by `cookie_consent_log.server_config_hash`, it preserves what the banner
 * looked like (groups, cookies, descriptions) when a consent was recorded.
 * New rows are only created when the banner configuration changes. The sales
 * channel and language columns are intentionally not enforced by foreign keys
 * so evidence survives their deletion.
 *
 * All fields are write-protected to the system scope: rows are only written via
 * raw SQL by the consent log route, the Admin API can read but not create or
 * modify snapshots.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
class CookieConsentConfigVersionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'cookie_consent_config_version';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CookieConsentConfigVersionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CookieConsentConfigVersionCollection::class;
    }

    public function since(): ?string
    {
        return '6.7.15.0';
    }

    /**
     * The per field WriteProtected flags below only cover fields sent in a write payload,
     * which a delete request has none of. This entity level protection also rejects
     * deletes, so recorded consent cannot be removed through the API. Reads stay allowed.
     */
    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new WriteProtection(Context::SYSTEM_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('config_hash', 'configHash'))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new JsonField('cookie_groups', 'cookieGroups'))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
        ]);
    }
}
