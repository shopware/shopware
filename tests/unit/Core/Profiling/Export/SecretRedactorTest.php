<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\SecretRedactor;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SecretRedactor::class)]
class SecretRedactorTest extends TestCase
{
    private SecretRedactor $redactor;

    protected function setUp(): void
    {
        $this->redactor = new SecretRedactor();
    }

    #[DataProvider('secretKeys')]
    public function testDetectsSecretKeys(int|string $key, bool $expected): void
    {
        static::assertSame($expected, $this->redactor->isSecretKey($key));
    }

    public static function secretKeys(): \Generator
    {
        yield 'authorization header' => ['authorization', true];
        yield 'cookie header' => ['cookie', true];
        yield 'set-cookie header' => ['set-cookie', true];
        yield 'cookie map of the request panel' => ['request_cookies', true];
        yield 'session attributes of the request panel' => ['session_attributes', true];
        yield 'session attributes in camel case' => ['sessionAttributes', true];
        yield 'Shopware context token header' => ['sw-context-token', true];
        yield 'sales channel access key header' => ['sw-access-key', true];
        yield 'api key header' => ['x-api-key', true];
        yield 'secret env var' => ['APP_SECRET', true];
        yield 'password env var' => ['INSTALL_ADMIN_PASSWORD', true];
        yield 'dsn env var' => ['MAILER_DSN', true];
        yield 'server cookie var' => ['HTTP_COOKIE', true];
        yield 'basic auth password' => ['PHP_AUTH_PW', true];
        yield 'camel case token property' => ['contextToken', true];
        yield 'mangled protected property' => ["\0*\0token", true];
        yield 'private key in camel case' => ['privateKey', true];
        yield 'profiler token header stays readable' => ['X-Debug-Token', false];
        yield 'profiler token link stays readable' => ['x-debug-token-link', false];
        yield 'stopwatch token stays readable' => ['_stopwatch_token', false];
        yield 'word that only starts like token' => ['tokenizer', false];
        yield 'composer auth env var' => ['COMPOSER_AUTH', true];
        yield 'http client bearer option' => ['auth_bearer', true];
        yield 'short password env var' => ['DB_PASS', true];
        yield 'passphrase env var' => ['JWT_PASSPHRASE', true];
        yield 'upper case env key' => ['ENCRYPTION_KEY', true];
        yield 'lower case key name stays readable' => ['cacheKey', false];
        yield 'oauth as one word stays readable' => ['oauth_provider', false];
        yield 'compiler pass in a trace frame stays readable' => ['/app/vendor/symfony/dependency-injection/Compiler/ResolveClassPass.php:59', false];
        yield 'database url relies on value redaction' => ['DATABASE_URL', false];
        yield 'session metadata is no secret' => ['session_metadata', false];
        yield 'list index' => [3, false];
        yield 'empty key' => ['', false];
    }

    #[DataProvider('secretStrings')]
    public function testRedactsSecretPatternsInStrings(string $value, string $expected): void
    {
        static::assertSame($expected, $this->redactor->redactString($value));
    }

    public static function secretStrings(): \Generator
    {
        yield 'bearer header value' => ['Bearer abc.def-123', 'Bearer [redacted]'];
        yield 'basic header value' => ['Basic dXNlcjpwYXNz', 'Basic [redacted]'];
        yield 'basic as an ordinary word stays' => ['Basic information about the cart', 'Basic information about the cart'];
        yield 'jwt inside a text' => ['token was eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxMjM0In0.c2lnbmF0dXJl here', 'token was [redacted] here'];
        yield 'password of a dsn' => ['mysql://root:s3cret@database:3306/shopware', 'mysql://root:[redacted]@database:3306/shopware'];
        yield 'password only dsn' => ['redis://s3cret@valkey:6379', 'redis://[redacted]@valkey:6379'];
        yield 'email in a query stays' => ['https://shop.test?email=a@b.c', 'https://shop.test?email=a@b.c'];
        yield 'cli option with equals' => ['bin/console user:create admin --password=\'s3cret\' --profile', 'bin/console user:create admin --password=[redacted] --profile'];
        yield 'cli option with a space' => ['bin/console integration:create --secret-access-key abc123 --admin', 'bin/console integration:create --secret-access-key [redacted] --admin'];
        yield 'curl header and form body' => ['curl --header \'Authorization: Bearer abc\' --data-raw \'client_secret=xyz&grant_type=client_credentials\'', 'curl --header \'Authorization: [redacted]\' --data-raw \'client_secret=[redacted]&grant_type=client_credentials\''];
        yield 'verbose request header lines' => ["> GET / HTTP/1.1\r\n> Authorization: Basic dXNlcg==\r\n> Accept: */*", "> GET / HTTP/1.1\r\n> Authorization: [redacted]\r\n> Accept: */*"];
        yield 'sql comment and time stay' => ['-- product-listing-route::loading::read SELECT 1 /* 12:30 */', '-- product-listing-route::loading::read SELECT 1 /* 12:30 */'];
        yield 'url without credentials stays' => ['https://shop.example/detail/1', 'https://shop.example/detail/1'];
        yield 'secret query parameter' => ['/account?access_token=abc&page=2', '/account?access_token=[redacted]&page=2'];
        yield 'ordinary query parameter stays' => ['/search?search=shirt&order=topseller', '/search?search=shirt&order=topseller'];
    }

    public function testRedactsKnownSecretsAnywhereInStrings(): void
    {
        $redactor = $this->redactor->withKnownSecrets(['0123456789abcdef0123456789abcdef']);

        static::assertSame('WHERE token = [redacted]', $redactor->redactString('WHERE token = 0123456789abcdef0123456789abcdef'));
        static::assertSame('0123456789abcdef0123456789abcdef', $this->redactor->redactString('0123456789abcdef0123456789abcdef'));
    }

    public function testLearnsValuesOfDirectlyNamedSecrets(): void
    {
        $data = (new VarCloner())->cloneVar([
            'request_headers' => [
                'sw-context-token' => '0123456789abcdef0123456789abcdef',
                'authorization' => ['Bearer eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxIn0.c2ln'],
                'accept' => 'text/html,application/xhtml+xml',
            ],
            'request_server' => [
                'APP_SECRET' => 'short',
            ],
            'session_attributes' => [
                'sw-rule-ids' => ['019c03efb81c702d9fc44d7131968990'],
                'sw-context-token' => 'fedcba9876543210fedcba9876543210',
            ],
        ]);

        static::assertSame(
            [
                '0123456789abcdef0123456789abcdef',
                'eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxIn0.c2ln',
                'fedcba9876543210fedcba9876543210',
            ],
            $this->redactor->learnSecrets($data),
        );
    }

    /**
     * @param array<array-key, mixed>|null $expected
     */
    #[DataProvider('bodies')]
    public function testDecodesRequestBodies(string $body, ?array $expected): void
    {
        static::assertSame($expected, $this->redactor->decodeBody($body));
    }

    public static function bodies(): \Generator
    {
        yield 'json object' => ['{"email":"a@b.c","password":"x"}', ['email' => 'a@b.c', 'password' => 'x']];
        yield 'json list' => ['[1,2]', [1, 2]];
        yield 'form encoded fields' => ['username=admin&password=x', ['username' => 'admin', 'password' => 'x']];
        yield 'invalid json stays a string' => ['{"email":', null];
        yield 'plain text stays a string' => ['hello world', null];
        yield 'empty body' => ['  ', null];
    }
}
