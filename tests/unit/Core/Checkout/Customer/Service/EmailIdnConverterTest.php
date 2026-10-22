<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Polyfill\Intl\Idn\Idn;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(EmailIdnConverter::class)]
class EmailIdnConverterTest extends TestCase
{
    #[DataProvider('getEmailListProvider')]
    public function testDecode(string $asciiMail, string $utf8Mail): void
    {
        $decodeResult = EmailIdnConverter::decode($asciiMail);

        static::assertSame($utf8Mail, $decodeResult);
    }

    #[DataProvider('getEmailListProvider')]
    public function testEncode(string $asciiMail, string $utf8Mail): void
    {
        $encodeResult = EmailIdnConverter::encode($utf8Mail);

        static::assertSame($asciiMail, $encodeResult);
    }

    public function testEncodeAndDecodePreserveIdnaDeviationCharacters(): void
    {
        $emails = [
            'customer@straße.de',
            'customer@οδός.gr',
            'customer@نامه‌ای.ir',
            'customer@क्‍ष.example',
        ];

        foreach ($emails as $email) {
            $encoded = EmailIdnConverter::encode($email);

            static::assertNotSame($email, $encoded);
            static::assertSame($email, EmailIdnConverter::decode($encoded));
        }
    }

    public function testEncodeKeepsSharpSAndAsciiSAsDifferentDomains(): void
    {
        static::assertSame('customer@xn--strae-oqa.de', EmailIdnConverter::encode('customer@straße.de'));
        static::assertSame('customer@strasse.de', EmailIdnConverter::encode('customer@strasse.de'));
        static::assertSame('täst@xn--strae-oqa.de', EmailIdnConverter::encode('täst@straße.de'));
    }

    public function testFailedConversionLeavesTheOriginalAddressForValidation(): void
    {
        static::assertFalse(idn_to_ascii('täst..de', \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46));
        static::assertSame('customer@täst..de', EmailIdnConverter::encode('customer@täst..de'));
    }

    public function testExplicitOptionsMatchSymfonyPolyfill(): void
    {
        foreach (['straße.de', 'οδός.gr', 'نامه‌ای.ir', 'क्‍ष.example', 'example.com'] as $domain) {
            $ascii = idn_to_ascii($domain, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46);
            static::assertIsString($ascii);

            static::assertSame(
                Idn::idn_to_ascii($domain, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46),
                $ascii
            );
            static::assertSame(
                Idn::idn_to_utf8($ascii, \IDNA_NONTRANSITIONAL_TO_UNICODE, \INTL_IDNA_VARIANT_UTS46),
                idn_to_utf8($ascii, \IDNA_NONTRANSITIONAL_TO_UNICODE, \INTL_IDNA_VARIANT_UTS46)
            );
        }
    }

    public function testEncodeInDataBagWithoutEmail(): void
    {
        $dataBag = new DataBag();

        EmailIdnConverter::encodeDataBag($dataBag);

        static::assertEquals(new DataBag(), $dataBag);
    }

    public function testDecodeInDataBag(): void
    {
        $dataBag = new DataBag(['email' => 'customer@xn--strae-oqa.de']);

        EmailIdnConverter::decodeDataBag($dataBag);

        static::assertSame('customer@straße.de', $dataBag->get('email'));
    }

    #[DataProvider('getEmailListProvider')]
    public function testEncodeInDataBag(string $asciiMail, string $utf8Mail): void
    {
        $dataBag = new DataBag();
        $dataBag->set('email', $utf8Mail);

        EmailIdnConverter::encodeDataBag($dataBag);

        static::assertSame($asciiMail, $dataBag->get('email'));
    }

    #[DataProvider('getEmailListProvider')]
    public function testEncodeInDataBagWithName(string $asciiMail, string $utf8Mail): void
    {
        $dataBag = new DataBag();
        $dataBag->set('emailConfirmation', $utf8Mail);

        EmailIdnConverter::encodeDataBag($dataBag, 'emailConfirmation');

        static::assertSame($asciiMail, $dataBag->get('emailConfirmation'));
    }

    public static function getEmailListProvider(): \Generator
    {
        yield 'email with umlauts' => ['test@xn--tst-qla.de', 'test@täst.de'];
        yield 'email without umlauts' => ['test@test.de', 'test@test.de'];
        yield 'invalid email: local part with umlauts' => ['täst@test.de', 'täst@test.de'];
        yield 'invalid email: missing domain part' => ['täst@', 'täst@'];
        yield 'invalid email: string with umlauts' => ['tä', 'tä'];
        yield 'invalid email: empty string' => ['', ''];
        yield 'invalid email: two @' => ['test@täest@.de', 'test@täest@.de'];
    }
}
