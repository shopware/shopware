<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Sso\TokenService;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\SsoException;
use Shopware\Core\Framework\Sso\TokenService\ParsedIdToken;
use Shopware\Tests\Integration\Core\Framework\Sso\Helper\FakeTokenGenerator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ParsedIdToken::class)]
class ParsedIdTokenTest extends TestCase
{
    private FakeTokenGenerator $tokenGenerator;

    protected function setUp(): void
    {
        $this->tokenGenerator = new FakeTokenGenerator();
    }

    public function testCreateFromDataSet(): void
    {
        $token = $this->tokenGenerator->generate();
        $parser = new Parser(new JoseEncoder());
        $parsed = $parser->parse($token);
        static::assertInstanceOf(Plain::class, $parsed);

        $result = ParsedIdToken::createFromDataSet($parsed->claims());

        static::assertSame('fake-subject', $result->sub);
        static::assertSame('fake@email.com', $result->email);
    }

    #[DataProvider('invalidData')]
    public function testCreateFromDataSetShouldThrowException(DataSet $dataSet, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(new SsoException(0, '0', $expectedExceptionMessage));

        ParsedIdToken::createFromDataSet($dataSet);
    }

    /**
     * @return \Generator<string, array{DataSet, string}>
     */
    public static function invalidData(): \Generator
    {
        yield 'All is not set' => [
            new DataSet([], ''),
            'ID-Token not valid: [exp] This field is missing., [sub] This field is missing., [email] This field is missing.',
        ];

        yield 'All is NULL' => [
            new DataSet(['exp' => null, 'sub' => null, 'email' => null, 'preferred_username' => null, 'given_name' => null, 'family_name' => null], ''),
            'ID-Token not valid: [exp] is empty, [sub] is empty, [email] is empty',
        ];

        yield 'All is blank' => [
            new DataSet(['exp' => '', 'sub' => '', 'email' => '', 'preferred_username' => '', 'given_name' => '', 'family_name' => ''], ''),
            'ID-Token not valid: [exp] is empty, [sub] is empty, [email] is empty',
        ];

        yield 'exp is blank' => [
            new DataSet(['exp' => '', 'sub' => 'sub', 'email' => 'foo@bar.baz', 'preferred_username' => 'preferred_username', 'given_name' => 'given_name', 'family_name' => 'family_name'], ''),
            'ID-Token not valid: [exp] is empty',
        ];

        yield 'sub is blank' => [
            new DataSet(['exp' => 'exp', 'sub' => '', 'email' => 'foo@bar.baz', 'preferred_username' => 'preferred_username', 'given_name' => 'given_name', 'family_name' => 'family_name'], ''),
            'ID-Token not valid: [sub] is empty',
        ];

        yield 'email is blank' => [
            new DataSet(['exp' => 'exp', 'sub' => 'sub', 'email' => '', 'preferred_username' => 'preferred_username', 'given_name' => 'given_name', 'family_name' => 'family_name'], ''),
            'ID-Token not valid: [email] is empty',
        ];

        yield 'email is invalid' => [
            new DataSet(['exp' => 'exp', 'sub' => 'sub', 'email' => 'invalid', 'preferred_username' => 'preferred_username', 'given_name' => 'given_name', 'family_name' => 'family_name'], ''),
            'ID-Token not valid: [email] is a invalid email address',
        ];
    }

    #[DataProvider('nullOrEmptyValues')]
    public function testUsernameDefaultsToEmailWhenNullOrEmpty(?string $value, string $expected): void
    {
        $this->tokenGenerator->setEmail($expected);
        $this->tokenGenerator->setPreferredUsername($value);

        $result = $this->parseToken($this->tokenGenerator);
        static::assertSame($expected, $result->username);
    }

    #[DataProvider('nullOrEmptyValues')]
    public function testGivenNameDefaultsToEmailWhenNullOrEmpty(?string $value, string $expected): void
    {
        $this->tokenGenerator->setEmail($expected);
        $this->tokenGenerator->setGivenName($value);

        $result = $this->parseToken($this->tokenGenerator);
        static::assertSame($expected, $result->givenName);
    }

    #[DataProvider('nullOrEmptyValues')]
    public function testFamilyNameDefaultsToEmailWhenNullOrEmpty(?string $value, string $expected): void
    {
        $this->tokenGenerator->setEmail($expected);
        $this->tokenGenerator->setFamilyName($value);

        $result = $this->parseToken($this->tokenGenerator);
        static::assertSame($expected, $result->familyName);
    }

    /**
     * @return \Generator<string, array{value: ?string, expected: string}>
     */
    public static function nullOrEmptyValues(): \Generator
    {
        yield 'null' => [
            'value' => null,
            'expected' => 'foo@bar.baz',
        ];
        yield 'empty string' => [
            'value' => '',
            'expected' => 'foo@bar.baz',
        ];
    }

    private function parseToken(FakeTokenGenerator $tokenGenerator): ParsedIdToken
    {
        $parsed = (new Parser(new JoseEncoder()))->parse($tokenGenerator->generate());
        static::assertInstanceOf(Plain::class, $parsed);

        return ParsedIdToken::createFromDataSet($parsed->claims());
    }
}
