<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreatedByField::class)]
class CreatedByFieldTest extends TestCase
{
    #[IgnoreDeprecations]
    public function testGetAllowedWriteScopesDefaultsToSystemScopeBeforeV680(): void
    {
        $this->expectUserDeprecationMessage('Since shopware/core : Not passing $allowedWriteScopes to Shopware\\Core\\Framework\\DataAbstractionLayer\\Field\\CreatedByField::__construct() will include Context::CRUD_API_SCOPE by default in v6.8.0. Pass the desired scopes explicitly to keep the current behavior.');

        Feature::fake([], function (): void {
            $field = new CreatedByField();

            static::assertSame([Context::SYSTEM_SCOPE], $field->getAllowedWriteScopes());
        });
    }

    public function testGetAllowedWriteScopesUsesExplicitScopes(): void
    {
        $field = new CreatedByField([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE]);

        static::assertSame([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE], $field->getAllowedWriteScopes());
    }

    public function testGetAllowedWriteScopesDefaultsToCrudScopeInV680(): void
    {
        Feature::fake(['v6.8.0.0'], function (): void {
            $field = new CreatedByField();

            static::assertSame([Context::SYSTEM_SCOPE, Context::CRUD_API_SCOPE], $field->getAllowedWriteScopes());
        });
    }

    public function testExplicitScopesStayUntouchedInV680(): void
    {
        Feature::fake(['v6.8.0.0'], function (): void {
            $field = new CreatedByField([Context::SYSTEM_SCOPE]);

            static::assertSame([Context::SYSTEM_SCOPE], $field->getAllowedWriteScopes());
        });
    }
}
