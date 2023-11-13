<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\OAuth;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Encoding\JoseEncoder;
use League\OAuth2\Server\AuthorizationValidators\AuthorizationValidatorInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\BearerTokenValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

/**
 * @internal
 */
class BearerTokenValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testValidationFailWhenTokenExpired(): void
    {
        $this->expectException(OAuthServerException::class);
        $connection = $this->getContainer()->get(Connection::class);
        $admin = TestUser::createNewTestUser($connection, ['product:read']);

        $request = new ServerRequest('GET', $_SERVER['APP_URL']);

        $currentTimestamp = (new \DateTime())->getTimestamp();

        $fakeClaims = [
            'aud' => 'administration',
            'jti' => '0dfaa92d1cda2bfe24c08e82cafa10687b6ea3e242186712c4b27508ccc5d43271d1863805460c44',
            'iat' => $currentTimestamp - 1, // make the token expired
            'nbf' => 1529436192,
            'exp' => 1529439792,
            'sub' => '7261d26c3e36451095afa7c05f8732b5',
            'scopes' => ['write'],
        ];

        $expiredToken = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjBkZmFhOTJkMWNkYTJiZmUyNGMwOGU4MmNhZmExMDY4N2I2ZWEzZTI0MjE4NjcxMmM0YjI3NTA4Y2NjNWQ0MzI3MWQxODYzODA1NDYwYzQ0In0.'
            . (new JoseEncoder())->base64UrlEncode(json_encode($fakeClaims, \JSON_THROW_ON_ERROR))
            . '.DBYbAWNpwxGL6QngLidboGbr2nmlAwjYcJIqN02sRnZNNFexy9V6uyQQ-8cJ00anwxKhqBovTzHxtXBMhZ47Ix72hxNWLjauKxQlsHAbgIKBDRbJO7QxgOU8gUnSQiXzRzKoX6XBOSHXFSUJ239lF4wai7621aCNFyEvlwf1JZVILsLjVkyIBhvuuwyIPbpEETui19BBaJ0eQZtjXtpzjsWNq1ibUCQvurLACnNxmXIj8xkSNenoX5B4p3R1gbDFuxaNHkGgsrQTwkDtmZxqCb3_0AgFL3XX0mpO5xsIJAI_hLHDPvv5m0lTQgMRrlgNdfE7ecI4GLHMkDmjWoNx_A';

        $request = $request->withHeader('authorization', $expiredToken);

        $request = $request->withAttribute(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID, $admin->getUserId());

        $userRepository = $this->getContainer()->get('user.repository');

        // Change user password
        $userRepository->update([[
            'id' => $admin->getUserId(),
            'password' => Uuid::randomHex(),
        ]], Context::createDefaultContext());

        $mockDecoratedValidator = $this->getMockBuilder(AuthorizationValidatorInterface::class)->disableOriginalConstructor()->getMock();
        $mockDecoratedValidator->method('validateAuthorization')->willReturn($request);

        $bearerTokenValidator = new BearerTokenValidator(
            $mockDecoratedValidator,
            $connection,
            $this->getContainer()->get('shopware.jwt_config')
        );

        $bearerTokenValidator->validateAuthorization($request);
    }
}
