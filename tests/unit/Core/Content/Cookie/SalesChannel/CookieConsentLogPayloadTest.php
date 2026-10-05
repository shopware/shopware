<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\SalesChannel\CookieConsentLogPayload;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieConsentLogPayload::class)]
class CookieConsentLogPayloadTest extends TestCase
{
    public function testAValidPayloadHasNoViolations(): void
    {
        $payload = new CookieConsentLogPayload('f47ac10b-58cc-4372-a567-0e02b2c3d479', CookieConsentAction::ACCEPT_SELECTED, ['lorem']);

        static::assertCount(0, $this->validate($payload));
    }

    public function testMissingAcceptedCookiesIsAValidEmptySelection(): void
    {
        $payload = new CookieConsentLogPayload('visitor-a', CookieConsentAction::ACCEPT_SELECTED);

        static::assertSame([], $payload->acceptedCookies);
        static::assertCount(0, $this->validate($payload));
    }

    /**
     * @param array<mixed> $acceptedCookies
     * @param non-empty-string $propertyPath
     */
    #[DataProvider('invalidPayloadProvider')]
    public function testInvalidPayloadsAreRejected(string $consentId, array $acceptedCookies, string $propertyPath): void
    {
        // @phpstan-ignore argument.type (invalid input on purpose, the validator has to catch it)
        $violations = $this->validate(new CookieConsentLogPayload($consentId, CookieConsentAction::ACCEPT_SELECTED, $acceptedCookies));

        static::assertGreaterThan(0, \count($violations));
        static::assertStringStartsWith($propertyPath, $violations->get(0)->getPropertyPath());
    }

    /**
     * @return iterable<string, array{string, array<mixed>, non-empty-string}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'empty consent id' => ['', [], 'consentId'];
        yield 'consent id with forbidden characters' => ['a b/c', [], 'consentId'];
        yield 'consent id too long' => [str_repeat('a', 65), [], 'consentId'];
        yield 'accepted cookies no list' => ['visitor-a', ['key' => 'value'], 'acceptedCookies'];
        yield 'accepted cookies with non strings' => ['visitor-a', ['lorem', 42], 'acceptedCookies'];
        yield 'accepted cookies with an empty name' => ['visitor-a', [''], 'acceptedCookies'];
        yield 'accepted cookie name too long' => ['visitor-a', [str_repeat('a', 256)], 'acceptedCookies'];
        yield 'too many accepted cookies' => ['visitor-a', array_fill(0, 501, 'lorem'), 'acceptedCookies'];
    }

    private function validate(CookieConsentLogPayload $payload): ConstraintViolationListInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($payload);
    }
}
