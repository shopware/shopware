<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('slow')]
class UserConfigControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function setup(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], []);
    }

    protected function tearDown(): void
    {
        $this->resetBrowser();
    }

    public function testGetConfigMe(): void
    {
        $configKey = 'me.read';

        $this->getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $this->getUserId(),
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey]]);
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([$configKey => ['content']], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testGetAllConfigMe(): void
    {
        $configKey = 'me.read';

        $this->getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $this->getUserId(),
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_info/config-me');
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([$configKey => ['content']], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testGetNullConfigMe(): void
    {
        $configKey = 'me.config';
        $ids = new IdsCollection();

        // Different user
        $user = [
            'id' => $ids->get('user'),
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'foobar',
            'localeId' => $this->getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'aclRoles' => [],
        ];

        $this->getContainer()->get('user.repository')->create([$user], Context::createDefaultContext());

        $this->getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $ids->get('user'),
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey]]);
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);

        // Different Key
        $userId = $this->getUserId();

        $this->getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $userId,
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());
        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => ['random-key']]);
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testUpdateConfigMe(): void
    {
        $configKey = 'me.config';
        $anotherConfigKey = 'random.key';
        $anotherValue = 'random.value';

        $this->getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $this->getUserId(),
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $newValue = 'another-content';
        $this->getBrowser()->request('POST', '/api/_info/config-me', [], [], [], json_encode([
            $configKey => [$newValue],
            $anotherConfigKey => [$anotherValue],
        ], \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey, $anotherConfigKey]]);
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([
            $configKey => [$newValue],
            $anotherConfigKey => [$anotherValue],
        ], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testCreateConfigMe(): void
    {
        $configKey = 'me.config';
        $newValue = 'another-content';
        $this->getBrowser()->request('POST', '/api/_info/config-me', [], [], [], json_encode([
            $configKey => [$newValue],
        ], \JSON_THROW_ON_ERROR));

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey]]);
        $response = $this->getBrowser()->getResponse();
        $content = $response->getContent();
        static::assertIsString($content);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame([$configKey => [$newValue]], json_decode($content, true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testCreateWithSendingEmptyParameter(): void
    {
        $this->getBrowser()->request('POST', '/api/_info/config-me');
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->request('POST', '/api/_info/config-me', [], [], [], json_encode([], \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function getUserId(): string
    {
        $context = $this->getBrowser()->getServerParameter(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);
        $source = $context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        $userId = $source->getUserId();
        static::assertIsString($userId);

        return Uuid::fromBytesToHex($userId);
    }
}
