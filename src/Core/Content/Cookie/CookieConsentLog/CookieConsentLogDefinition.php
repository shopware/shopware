<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\CookieConsentLog;

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
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * Anonymous audit trail of storefront cookie consent decisions (GDPR Recital 42).
 *
 * One row per visitor consent action. Contains no visitor identifiers by design;
 * it proves that consent was collected at a given time with a given banner
 * configuration (`server_config_hash` references `cookie_consent_config_version`),
 * not who gave it. The sales channel and language columns are intentionally
 * not enforced by foreign keys so evidence survives their deletion.
 *
 * All fields are write-protected to the system scope: rows are only written via
 * raw SQL by the consent log route, the Admin API can read but not create or
 * modify evidence.
 *
 * `group_decisions` and `accepted_cookies` are both derived server-side from the
 * cookie names the visitor selected. The raw cookie names are kept alongside the
 * per-group verdict so a row stays auditable if the definition of a partial
 * acceptance ever changes.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
class CookieConsentLogDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'cookie_consent_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CookieConsentLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CookieConsentLogCollection::class;
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

            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),

            (new StringField('consent_action', 'consentAction', 32))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new JsonField('group_decisions', 'groupDecisions'))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new ListField('accepted_cookies', 'acceptedCookies', StringField::class))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new StringField('server_config_hash', 'serverConfigHash'))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
            (new StringField('rendered_config_hash', 'renderedConfigHash'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
        ]);
    }
}
