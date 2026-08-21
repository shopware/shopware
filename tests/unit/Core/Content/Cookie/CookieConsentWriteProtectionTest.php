<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieConsentConfigVersion\CookieConsentConfigVersionDefinition;
use Shopware\Core\Content\Cookie\CookieConsentLog\CookieConsentLogDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The tables are consent evidence: their value is that they were only ever written
 * by the consent log route. Every field must therefore reject writes from the API
 * scopes, otherwise evidence could be fabricated or edited through the
 * auto-generated Admin API CRUD endpoints.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieConsentLogDefinition::class)]
#[CoversClass(CookieConsentConfigVersionDefinition::class)]
class CookieConsentWriteProtectionTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<EntityDefinition>}>
     */
    public static function definitionProvider(): iterable
    {
        yield 'cookie_consent_log' => [CookieConsentLogDefinition::class];
        yield 'cookie_consent_config_version' => [CookieConsentConfigVersionDefinition::class];
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    #[DataProvider('definitionProvider')]
    public function testEveryFieldIsWriteProtectedToTheSystemScope(string $definitionClass): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [$definitionClass],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $definition = $registry->get($definitionClass);

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
