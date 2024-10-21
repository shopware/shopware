<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

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

    public function testEncodeInDataBagWithoutEmail(): void
    {
        $dataBag = new DataBag();

        EmailIdnConverter::encodeDataBag($dataBag);

        static::assertEquals(new DataBag(), $dataBag);
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
