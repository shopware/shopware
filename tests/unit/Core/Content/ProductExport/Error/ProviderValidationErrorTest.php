<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\Error\ProviderValidationError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProviderValidationError::class)]
class ProviderValidationErrorTest extends TestCase
{
    public function testBuildsExpectedErrorPayloadWithLine(): void
    {
        $error = new ProviderValidationError('export-id', 'open-ai', 'return_policy', 'Return policy is missing.', 4);

        static::assertSame('provider-validation-failedexport-idopen-aireturn_policy4', $error->getId());
        static::assertSame('provider-validation-failed', $error->getMessageKey());
        static::assertSame(
            [
                'provider' => 'open-ai',
                'field' => 'return_policy',
                'error' => 'Return policy is missing.',
                'line' => 4,
            ],
            $error->getParameters()
        );

        $messages = $error->getErrorMessages();

        static::assertCount(1, $messages);
        static::assertSame('Return policy is missing.', $messages[0]->getMessage());
        static::assertSame(4, $messages[0]->getLine());
        static::assertNull($messages[0]->getColumn());
        static::assertSame('The export did not satisfy the provider requirements', $error->getMessage());
    }

    public function testBuildsGlobalIdentifierWhenLineIsMissing(): void
    {
        $error = new ProviderValidationError('export-id', 'open-ai', 'return_policy', 'Return policy is missing.');

        static::assertSame('provider-validation-failedexport-idopen-aireturn_policyglobal', $error->getId());
        static::assertSame(
            [
                'provider' => 'open-ai',
                'field' => 'return_policy',
                'error' => 'Return policy is missing.',
                'line' => null,
            ],
            $error->getParameters()
        );
        static::assertNull($error->getErrorMessages()[0]->getLine());
    }

    public function testJsonSerializeContainsNormalizedErrorInformation(): void
    {
        $error = new ProviderValidationError('export-id', 'open-ai', 'return_policy', 'Return policy is missing.', 4);

        $serialized = $error->jsonSerialize();

        static::assertSame('provider-validation-failedexport-idopen-aireturn_policy4', $serialized['key']);
        static::assertSame('provider-validation-failed', $serialized['messageKey']);
        static::assertSame('The export did not satisfy the provider requirements', $serialized['message']);
        static::assertCount(1, $serialized['errorMessages']);
    }
}
