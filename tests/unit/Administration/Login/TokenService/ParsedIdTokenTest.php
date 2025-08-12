<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Login\TokenService;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Login\LoginException;
use Shopware\Administration\Login\TokenService\ParsedIdToken;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Administration\Login\Helper\FakeTokenGenerator;

/**
 * @internal
 */
#[Package('framework')]
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
        $this->expectExceptionObject(new LoginException(0, '0', $expectedExceptionMessage));

        ParsedIdToken::createFromDataSet($dataSet);
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
                new DataSet(['exp' => null, 'sub' => null, 'email' => null, 'preferred_username' => null, 'given_name' => null, 'family_name' => null], ''),
                'ID-Token not valid: [exp] is empty, [sub] is empty, [email] is empty',
            ],

            'All is blank' => [
                new DataSet(['exp' => '', 'sub' => '', 'email' => '', 'preferred_username' => '', 'given_name' => '', 'family_name' => ''], ''),
                'ID-Token not valid: [exp] is empty, [sub] is empty, [email] is empty',
            ],

            'exp is blank' => [
                new DataSet(['exp' => '', 'sub' => 'sub', 'email' => 'foo@bar.baz', 'preferred_username' => 'preferred_username', 'given_name' => 'given_name', 'family_name' => 'family_name'], ''),
                'ID-Token not valid: [exp] is empty',
            ],

            'sub is blank' => [
                new DataSet(['exp' => 'exp', 'sub' => '', 'email' => 'foo@bar.baz', 'preferred_username' => 'preferred_username', 'given_name' => 'given_name', 'family_name' => 'family_name'], ''),
                'ID-Token not valid: [sub] is empty',
            ],

            'email is blank' => [
                new DataSet(['exp' => 'exp', 'sub' => 'sub', 'email' => '', 'preferred_username' => 'preferred_username', 'given_name' => 'given_name', 'family_name' => 'family_name'], ''),
                'ID-Token not valid: [email] is empty',
            ],

            'email is invalid' => [
                new DataSet(['exp' => 'exp', 'sub' => 'sub', 'email' => 'invalid', 'preferred_username' => 'preferred_username', 'given_name' => 'given_name', 'family_name' => 'family_name'], ''),
                'ID-Token not valid: [email] is a invalid email address',
            ],
        ];
    }

    #[DataProvider('nullOrEmptyDataset')]
    public function testCreateFromDataSetShouldReturnEmailIfValueIsNullOrEmpty(bool $isNull, string $property): void
    {
        $tokenGenerator = new FakeTokenGenerator();
        $parser = new Parser(new JoseEncoder());
        $email = 'foo@bar.baz';
        $value = '';
        if ($isNull) {
            $value = null;
        }

        $tokenGenerator->setEmail($email);
        $setter = $this->getSetter($property);
        // @phpstan-ignore symplify.noDynamicName
        $tokenGenerator->$setter($value);

        $token = $tokenGenerator->generate();
        $parsed = $parser->parse($token);
        static::assertInstanceOf(Plain::class, $parsed);

        $result = ParsedIdToken::createFromDataSet($parsed->claims());
        // @phpstan-ignore symplify.noDynamicName
        static::assertSame($email, $result->$property);
    }

    /**
     * @return array<array<string, bool|string>>
     */
    public static function nullOrEmptyDataset(): array
    {
        return [
            [
                'isNull' => true,
                'property' => 'username',
            ],
            [
                'isNull' => false,
                'property' => 'username',
            ],
            [
                'isNull' => true,
                'property' => 'givenName',
            ],
            [
                'isNull' => false,
                'property' => 'givenName',
            ],
            [
                'isNull' => true,
                'property' => 'familyName',
            ],
            [
                'isNull' => false,
                'property' => 'familyName',
            ],
        ];
    }

    public function getSetter(string $property): string
    {
        $setterSuffix = $property;

        // add exception for username property because it is different in token generator
        if ($setterSuffix === 'username') {
            $setterSuffix = 'preferredUsername';
        }

        return 'set' . \ucfirst($setterSuffix);
    }
}
