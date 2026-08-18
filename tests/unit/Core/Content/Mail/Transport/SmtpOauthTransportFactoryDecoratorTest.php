<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Transport\SmtpOauthAuthenticator;
use Shopware\Core\Content\Mail\Transport\SmtpOauthTransportFactoryDecorator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SmtpOauthTransportFactoryDecorator::class)]
class SmtpOauthTransportFactoryDecoratorTest extends TestCase
{
    public function testCreateReturnsTransportUnmodifiedIfNotEsmtpTransport(): void
    {
        $dsn = new Dsn('smtp', 'localhost');

        $decorated = new EsmtpTransportFactory();

        $authenticator = static::createStub(SmtpOauthAuthenticator::class);

        $factory = new SmtpOauthTransportFactoryDecorator($decorated, $authenticator);

        $result = $factory->create($dsn);

        static::assertInstanceOf(EsmtpTransport::class, $result);

        $authenticators = (new \ReflectionProperty(EsmtpTransport::class, 'authenticators'))->getValue($result);

        static::assertNotContains($authenticator, $authenticators);
    }

    public function testCreateSetsAuthenticatorForEsmtpTransportWithSmtpOauthScheme(): void
    {
        $dsn = new Dsn('smtp', 'localhost', 'user', 'password', 123, [
            SmtpOauthTransportFactoryDecorator::OPTION_KEY_USE_OAUTH => true,
        ]);

        $decorated = new EsmtpTransportFactory();

        $authenticator = static::createStub(SmtpOauthAuthenticator::class);

        $factory = new SmtpOauthTransportFactoryDecorator($decorated, $authenticator);

        $result = $factory->create($dsn);

        static::assertInstanceOf(EsmtpTransport::class, $result);

        $authenticators = (new \ReflectionProperty(EsmtpTransport::class, 'authenticators'))->getValue($result);

        static::assertContains($authenticator, $authenticators);
    }

    public function testSupportsReturnsTrueIfDecoratedSupportsDsn(): void
    {
        $dsn = new Dsn('smtp', 'localhost');

        $decorated = new EsmtpTransportFactory();

        $authenticator = static::createStub(SmtpOauthAuthenticator::class);

        $factory = new SmtpOauthTransportFactoryDecorator($decorated, $authenticator);

        static::assertTrue($factory->supports($dsn));
    }

    public function testSupportsReturnsFalseIfDecoratedDoesNotSupportDsn(): void
    {
        $dsn = new Dsn('sendmail', 'localhost');

        $decorated = new EsmtpTransportFactory();

        $authenticator = static::createStub(SmtpOauthAuthenticator::class);

        $factory = new SmtpOauthTransportFactoryDecorator($decorated, $authenticator);

        static::assertFalse($factory->supports($dsn));
    }
}
