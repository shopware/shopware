<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login\TokenService;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\Exception\LoginException;
use Shopware\Administration\Login\TokenService\ParsedIdToken;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeTokenGenerator;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ParsedIdToken::class)]
class ParsedIdTokenTest extends TestCase
{
    public function testCreateFromDataSet(): void
    {
        $token = (new FakeTokenGenerator())->generate();
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
        try {
            ParsedIdToken::createFromDataSet($dataSet);
        } catch (LoginException $loginException) {
            static::assertSame($expectedExceptionMessage, $loginException->getMessage());
            static::assertSame(LoginException::LOGIN_INVALID_ID_TOKEN_DATA_SET, $loginException->getErrorCode());

            return;
        }

        static::fail('LoginException should have thrown: ' . $expectedExceptionMessage);
    }

    /**
     * @return array<string, array<int, DataSet|string>>
     */
    public static function invalidData(): array
    {
        return [
            'All is not set' => [
                new DataSet([], ''),
                'ID-Token not valid: [exp] This field is missing., [sub] This field is missing., [email] This field is missing.',
            ],

            'All is NULL' => [
                new DataSet(['exp' => null, 'sub' => null, 'email' => null], ''),
                'ID-Token not valid: [exp] is empty, [sub] is empty, [email] is empty',
            ],

            'All is blank' => [
                new DataSet(['exp' => '', 'sub' => '', 'email' => ''], ''),
                'ID-Token not valid: [exp] is empty, [sub] is empty, [email] is empty',
            ],

            'exp is blank' => [
                new DataSet(['exp' => '', 'sub' => 'sub', 'email' => 'foo@bar.baz'], ''),
                'ID-Token not valid: [exp] is empty',
            ],

            'sub is blank' => [
                new DataSet(['exp' => 'exp', 'sub' => '', 'email' => 'foo@bar.baz'], ''),
                'ID-Token not valid: [sub] is empty',
            ],

            'email is blank' => [
                new DataSet(['exp' => 'exp', 'sub' => 'sub', 'email' => ''], ''),
                'ID-Token not valid: [email] is empty',
            ],

            'email is invalid' => [
                new DataSet(['exp' => 'exp', 'sub' => 'sub', 'email' => 'invalid'], ''),
                'ID-Token not valid: [email] is a invalid email address',
            ],
        ];
    }
}
