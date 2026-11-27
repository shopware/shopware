<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\CollectorDataConverter;
use Shopware\Core\Profiling\Export\ExportLimits;
use Shopware\Core\Profiling\Export\SecretRedactor;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CollectorDataConverter::class)]
class CollectorDataConverterTest extends TestCase
{
    private CollectorDataConverter $converter;

    private ExportLimits $unlimited;

    protected function setUp(): void
    {
        $this->converter = new CollectorDataConverter();
        $this->unlimited = new ExportLimits(depth: 0, items: 0, string: 0);
    }

    public function testConvertsVarDumperDataWithClassNamesAndPropertyNames(): void
    {
        $data = (new VarCloner())->cloneVar(['route' => 'frontend.home.page', 'object' => new ConverterFixture('visible', 'hidden')]);

        static::assertSame(
            [
                'route' => 'frontend.home.page',
                'object' => [
                    '@class' => ConverterFixture::class,
                    'name' => 'visible',
                    'secretValue' => 'hidden',
                ],
            ],
            $this->converter->convert($data, $this->unlimited),
        );
    }

    public function testConvertsPlainObjectsScalarsAndSpecialValues(): void
    {
        $value = [
            'object' => new ConverterFixture('a', 'b'),
            'date' => new \DateTimeImmutable('2026-11-27T10:00:00+00:00'),
            'enum' => ConverterFixtureStatus::Open,
            'binary' => "\x01\x9c\x03\xef",
            'infinite' => \INF,
            'number' => 1.5,
            'flag' => true,
            'nothing' => null,
        ];

        static::assertSame(
            [
                'object' => ['@class' => ConverterFixture::class, 'name' => 'a', 'secretValue' => 'b'],
                'date' => '2026-11-27T10:00:00+00:00',
                'enum' => 'open',
                'binary' => '019c03ef',
                'infinite' => 'INF',
                'number' => 1.5,
                'flag' => true,
                'nothing' => null,
            ],
            $this->converter->convert($value, $this->unlimited),
        );
    }

    public function testMarksRecursiveObjects(): void
    {
        $node = new \stdClass();
        $node->self = $node;

        static::assertSame(
            ['@class' => \stdClass::class, 'self' => '[recursion stdClass]'],
            $this->converter->convert($node, $this->unlimited),
        );
    }

    public function testLimitsDepthItemsAndStringLength(): void
    {
        $value = [
            'list' => [1, 2, 3, 4, 5],
            'map' => ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
            'nested' => ['deeper' => ['deepest' => 1]],
            'text' => 'abcdefgh',
        ];

        static::assertSame(
            [
                'list' => [1, 2, 3, '[… 2 more]'],
                'map' => ['a' => 1, 'b' => 2, 'c' => 3, '…' => '[… 1 more]'],
                'nested' => ['deeper' => '[array(1) beyond depth]'],
                '…' => '[… 1 more]',
            ],
            $this->converter->convert($value, new ExportLimits(depth: 2, items: 3, string: 4)),
        );

        static::assertSame('abcd…[4 more chars]', $this->converter->convert('abcdefgh', new ExportLimits(depth: 2, items: 3, string: 4)));
    }

    public function testRedactsSecretKeysButKeepsStructureNamesAndFlags(): void
    {
        $value = (new VarCloner())->cloneVar([
            'request_headers' => ['cookie' => 'session-=abc', 'accept' => 'text/html'],
            'request_cookies' => ['session-' => 'abc', 'timezone' => 'Europe/Berlin'],
            'session_attributes' => ['sw-rule-ids' => ['019c03efb81c702d9fc44d7131968990'], 'empty' => '', 'missing' => null, 'flag' => true],
            'request_server' => ['DATABASE_URL' => 'mysql://root:root@database/shopware'],
        ]);

        static::assertSame(
            [
                'request_headers' => ['cookie' => '[redacted]', 'accept' => 'text/html'],
                'request_cookies' => ['session-' => '[redacted]', 'timezone' => '[redacted]'],
                'session_attributes' => ['sw-rule-ids' => ['[redacted]'], 'empty' => '', 'missing' => null, 'flag' => true],
                'request_server' => ['DATABASE_URL' => 'mysql://root:[redacted]@database/shopware'],
            ],
            $this->converter->convert($value, $this->unlimited, new SecretRedactor()),
        );
    }

    public function testDecodesAndRedactsRequestBodies(): void
    {
        $value = ['content' => '{"email":"a@b.c","password":"x"}', 'other' => ['content' => 'plain text']];

        static::assertSame(
            ['content' => ['email' => 'a@b.c', 'password' => '[redacted]'], 'other' => ['content' => 'plain text']],
            $this->converter->convert($value, $this->unlimited, new SecretRedactor()),
        );

        static::assertSame($value, $this->converter->convert($value, $this->unlimited));
    }

    public function testRedactsKnownSecretsBeforeTruncating(): void
    {
        $redactor = (new SecretRedactor())->withKnownSecrets(['0123456789abcdef0123456789abcdef']);

        static::assertSame(
            ['params' => ['x[redacted]', 'x0123456789abcdef0123456789abcdef']],
            [
                'params' => [
                    $this->converter->convert('x0123456789abcdef0123456789abcdef', new ExportLimits(depth: 0, items: 0, string: 12), $redactor),
                    $this->converter->convert('x0123456789abcdef0123456789abcdef', $this->unlimited),
                ],
            ],
        );
    }

    public function testWithoutRedactorSecretsStay(): void
    {
        $value = ['authorization' => 'Bearer abc', 'APP_SECRET' => 'x'];

        static::assertSame($value, $this->converter->convert($value, $this->unlimited));
    }
}

/**
 * @internal
 */
class ConverterFixture
{
    public function __construct(
        public string $name,
        private readonly string $secretValue,
    ) {
    }

    public function secretValue(): string
    {
        return $this->secretValue;
    }
}

/**
 * @internal
 */
enum ConverterFixtureStatus: string
{
    case Open = 'open';
}
