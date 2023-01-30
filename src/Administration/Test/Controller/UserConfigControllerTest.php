<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @group slow
 */
class UserConfigControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function setup(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], []);
    }

    public function tearDown(): void
    {
        $this->resetBrowser();
    }

    public function testGetConfigMe(): void
    {
        $configKey = 'me.read';

        $contextBrowser = $this->getBrowser()->getServerParameter(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        $userId = Uuid::fromBytesToHex($contextBrowser->getSource()->getUserId());

        $this->getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $userId,
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey]]);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertEquals([$configKey => ['content']], json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testGetAllConfigMe(): void
    {
        $configKey = 'me.read';

        $contextBrowser = $this->getBrowser()->getServerParameter(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        $userId = Uuid::fromBytesToHex($contextBrowser->getSource()->getUserId());

        $this->getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $userId,
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_info/config-me');
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertEquals([$configKey => ['content']], json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testGetNullConfigMe(): void
    {
        $configKey = 'me.config';
        $ids = new IdsCollection();

        //Different user
        $user = [
            'id' => $ids->get('user'),
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => 'password',
            'username' => 'foobar',
            'localeId' => $this->getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'aclRoles' => [],
        ];

        $this->getContainer()->get('user.repository')
            ->create([$user], Context::createDefaultContext());

        $this->getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $ids->get('user'),
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey]]);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertEquals([], json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data']);

        //Different Key
        $contextBrowser = $this->getBrowser()->getServerParameter(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        $userId = Uuid::fromBytesToHex($contextBrowser->getSource()->getUserId());

        $this->getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $userId,
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());
        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => ['random-key']]);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertEquals([], json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testUpdateConfigMe(): void
    {
        $configKey = 'me.config';
        $anotherConfigKey = 'random.key';
        $anotherValue = 'random.value';
        $contextBrowser = $this->getBrowser()->getServerParameter(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        $userId = Uuid::fromBytesToHex($contextBrowser->getSource()->getUserId());

        $this->getContainer()->get('user_config.repository')
            ->create([[
                'userId' => $userId,
                'key' => $configKey,
                'value' => ['content'],
            ]], Context::createDefaultContext());

        $newValue = 'another-content';
        $this->getBrowser()->request('POST', '/api/_info/config-me', [], [], [], json_encode([
            $configKey => [$newValue],
            $anotherConfigKey => [$anotherValue],
        ]));
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey, $anotherConfigKey]]);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertEquals([
            $configKey => [$newValue],
            $anotherConfigKey => [$anotherValue],
        ], json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testCreateConfigMe(): void
    {
        $configKey = 'me.config';

        $newValue = 'another-content';
        $this->getBrowser()->request('POST', '/api/_info/config-me', [], [], [], json_encode([
            $configKey => [$newValue],
        ]));

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/_info/config-me', ['keys' => [$configKey]]);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        static::assertEquals([$configKey => [$newValue]], json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['data']);
    }

    public function testCreateWithSendingEmptyParameter(): void
    {
        $this->getBrowser()->request('POST', '/api/_info/config-me', [], [], []);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->request('POST', '/api/_info/config-me', [], [], [], json_encode([]));
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
