<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Transport\SmtpOauthAuthenticator;
use Shopware\Core\Content\Mail\Transport\SmtpOauthTransportFactoryDecorator;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;

/**
 * @internal
 */
#[CoversClass(SmtpOauthTransportFactoryDecorator::class)]
class SmtpOauthTransportFactoryDecoratorTest extends TestCase
{
    public function testCreateReturnsTransportUnmodifiedIfNotEsmtpTransport(): void
    {
        $dsn = new Dsn('smtp', 'localhost');

        $decorated = new EsmtpTransportFactory();

        $authenticator = $this->createMock(SmtpOauthAuthenticator::class);

        $factory = new SmtpOauthTransportFactoryDecorator($decorated, $authenticator);

        $result = $factory->create($dsn);

        static::assertInstanceOf(EsmtpTransport::class, $result);

        $authenticators = ReflectionHelper::getPropertyValue($result, 'authenticators');

        static::assertNotContains($authenticator, $authenticators);
    }

    public function testCreateSetsAuthenticatorForEsmtpTransportWithSmtpOauthScheme(): void
    {
        $dsn = new Dsn('smtp', 'localhost', 'user', 'password', 123, [
            SmtpOauthTransportFactoryDecorator::OPTION_KEY_USE_OAUTH => true,
        ]);

        $decorated = new EsmtpTransportFactory();

        $authenticator = $this->createMock(SmtpOauthAuthenticator::class);

        $factory = new SmtpOauthTransportFactoryDecorator($decorated, $authenticator);

        $result = $factory->create($dsn);

        static::assertInstanceOf(EsmtpTransport::class, $result);

        $authenticators = ReflectionHelper::getPropertyValue($result, 'authenticators');

        static::assertContains($authenticator, $authenticators);
    }

    public function testSupportsReturnsTrueIfDecoratedSupportsDsn(): void
    {
        $dsn = new Dsn('smtp', 'localhost');

        $decorated = new EsmtpTransportFactory();

        $authenticator = $this->createMock(SmtpOauthAuthenticator::class);

        $factory = new SmtpOauthTransportFactoryDecorator($decorated, $authenticator);

        static::assertTrue($factory->supports($dsn));
    }

    public function testSupportsReturnsFalseIfDecoratedDoesNotSupportDsn(): void
    {
        $dsn = new Dsn('sendmail', 'localhost');

        $decorated = new EsmtpTransportFactory();

        $authenticator = $this->createMock(SmtpOauthAuthenticator::class);

        $factory = new SmtpOauthTransportFactoryDecorator($decorated, $authenticator);

        static::assertFalse($factory->supports($dsn));
    }
}
