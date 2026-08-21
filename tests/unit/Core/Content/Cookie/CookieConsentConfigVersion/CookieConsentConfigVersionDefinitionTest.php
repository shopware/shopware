<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\CookieConsentConfigVersion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieConsentConfigVersion\CookieConsentConfigVersionDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The table is consent evidence: it preserves what the cookie banner looked like
 * when consent was given. Every field must therefore reject writes from the API
 * scopes, otherwise snapshots could be fabricated or edited through the
 * auto-generated Admin API CRUD endpoints.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieConsentConfigVersionDefinition::class)]
class CookieConsentConfigVersionDefinitionTest extends TestCase
{
    public function testEveryFieldIsWriteProtectedToTheSystemScope(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CookieConsentConfigVersionDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $definition = $registry->get(CookieConsentConfigVersionDefinition::class);

        foreach ($definition->getFields() as $field) {
            // The primary key must stay writable, a row cannot be inserted without it.
            // created_at/updated_at are managed by the DAL itself.
            if ($field->is(PrimaryKey::class) || $field instanceof CreatedAtField || $field instanceof UpdatedAtField) {
                continue;
            }

            $flag = $field->getFlag(WriteProtected::class);

            static::assertInstanceOf(
                WriteProtected::class,
                $flag,
                \sprintf('Field "%s" of "%s" must be write-protected, the table is consent evidence', $field->getPropertyName(), $definition->getEntityName()),
            );
            static::assertTrue($flag->isAllowed(Context::SYSTEM_SCOPE));
            static::assertFalse($flag->isAllowed(Context::CRUD_API_SCOPE));
            static::assertFalse($flag->isAllowed(Context::USER_SCOPE));
        }
    }
}
