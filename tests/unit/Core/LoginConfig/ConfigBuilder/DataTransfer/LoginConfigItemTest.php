<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\LoginConfig\ConfigBuilder\DataTransfer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\LoginConfig\ConfigBuilder\LoginConfigItem;

class LoginConfigItemTest extends TestCase
{
    public function testFromArrayShouldCreateSuccessfully(): void
    {
        $key = 'key';
        $loginConfigItemArray = [
            'key' => 'key',
            'snippet_key' => 'snippet.Key',
            'icon' => 'icon.shopware',
            'class' => 'class',
            'client_id' => 'C7i3ntID',
            'client_secret' => 'c7i3ntS3cr3t',
            'redirect_uri' => 'http://redirect.uri',
            'base_url' => 'http://base.url',
            'additional_data' => ['key' => 'value'],
        ];

        $loginConfigItem = LoginConfigItem::fromArray($key, $loginConfigItemArray);

        static::assertSame($key, $loginConfigItem->configKey);
        static::assertSame($loginConfigItemArray['snippet_key'], $loginConfigItem->snippetKey);
        static::assertSame($loginConfigItemArray['icon'], $loginConfigItem->icon);
        static::assertSame($loginConfigItemArray['class'], $loginConfigItem->class);
        static::assertSame($loginConfigItemArray['client_id'], $loginConfigItem->clientId);
        static::assertSame($loginConfigItemArray['client_secret'], $loginConfigItem->clientSecret);
        static::assertSame($loginConfigItemArray['redirect_uri'], $loginConfigItem->redirectUri);
        static::assertSame($loginConfigItemArray['base_url'], $loginConfigItem->baseUrl);
        static::assertSame($loginConfigItemArray['additional_data'], $loginConfigItem->additionalData);
    }

    #[DataProvider('fromArrayDataProvider')]
    public function testFromArray(array $array, ?string $expectedMessage): void
    {
        try {
            $loginConfigItem = LoginConfigItem::fromArray('KEY', $array);
            static::assertSame('KEY', $loginConfigItem->configKey);
            static::assertSame($array['snippet_key'], $loginConfigItem->snippetKey);
            static::assertSame($array['icon'], $loginConfigItem->icon);
            static::assertSame($array['class'], $loginConfigItem->class);
            static::assertSame($array['client_id'], $loginConfigItem->clientId);
            static::assertSame($array['client_secret'], $loginConfigItem->clientSecret);
            static::assertSame($array['redirect_uri'], $loginConfigItem->redirectUri);
            static::assertSame($array['base_url'], $loginConfigItem->baseUrl);
        } catch (\Exception $e) {
            static::assertNotNull($expectedMessage);
            static::assertSame($e->getMessage(), $expectedMessage);
        }
    }

    public static function fromArrayDataProvider(): array
    {
        return [
            'valid' => [
                'array' => [
                    'snippet_key' => 'snippet.Key',
                    'icon' => 'icon.shopware',
                    'class' => 'class',
                    'client_id' => 'C7i3ntID',
                    'client_secret' => 'c7i3ntS3cr3t',
                    'redirect_uri' => 'http://redirect.uri',
                    'base_url' => 'http://base.url',
                    'additional_data' => ['key' => 'value'],
                ],
                'expectedMessage' => null,
            ],

            'missing snippet_key' => [
                'array' => [
                    'icon' => 'icon.shopware',
                    'class' => 'class',
                    'client_id' => 'C7i3ntID',
                    'client_secret' => 'c7i3ntS3cr3t',
                    'redirect_uri' => 'http://redirect.uri',
                    'base_url' => 'http://base.url',
                ],
                'expectedMessage' => 'Login config is incomplete. Required field(s) "[snippet_key]" missing.',
            ],

            'missing icon' => [
                'array' => [
                    'snippet_key' => 'snippet.Key',
                    'class' => 'class',
                    'client_id' => 'C7i3ntID',
                    'client_secret' => 'c7i3ntS3cr3t',
                    'redirect_uri' => 'http://redirect.uri',
                    'base_url' => 'http://base.url',
                ],
                'expectedMessage' => 'Login config is incomplete. Required field(s) "[icon]" missing.',
            ],

            'missing class' => [
                'array' => [
                    'snippet_key' => 'snippet.Key',
                    'icon' => 'icon.shopware',
                    'client_id' => 'C7i3ntID',
                    'client_secret' => 'c7i3ntS3cr3t',
                    'redirect_uri' => 'http://redirect.uri',
                    'base_url' => 'http://base.url',
                ],
                'expectedMessage' => 'Login config is incomplete. Required field(s) "[class]" missing.',
            ],

            'missing client_id' => [
                'array' => [
                    'snippet_key' => 'snippet.Key',
                    'icon' => 'icon.shopware',
                    'class' => 'class',
                    'client_secret' => 'c7i3ntS3cr3t',
                    'redirect_uri' => 'http://redirect.uri',
                    'base_url' => 'http://base.url',
                ],
                'expectedMessage' => 'Login config is incomplete. Required field(s) "[client_id]" missing.',
            ],

            'missing client_secret' => [
                'array' => [
                    'snippet_key' => 'snippet.Key',
                    'icon' => 'icon.shopware',
                    'class' => 'class',
                    'client_id' => 'C7i3ntID',
                    'redirect_uri' => 'http://redirect.uri',
                    'base_url' => 'http://base.url',
                ],
                'expectedMessage' => 'Login config is incomplete. Required field(s) "[client_secret]" missing.',
            ],

            'missing redirect_uri' => [
                'array' => [
                    'snippet_key' => 'snippet.Key',
                    'icon' => 'icon.shopware',
                    'class' => 'class',
                    'client_id' => 'C7i3ntID',
                    'client_secret' => 'c7i3ntS3cr3t',
                    'base_url' => 'http://base.url',
                ],
                'expectedMessage' => 'Login config is incomplete. Required field(s) "[redirect_uri]" missing.',
            ],

            'missing base_url' => [
                'array' => [
                    'snippet_key' => 'snippet.Key',
                    'icon' => 'icon.shopware',
                    'class' => 'class',
                    'client_id' => 'C7i3ntID',
                    'client_secret' => 'c7i3ntS3cr3t',
                    'redirect_uri' => 'http://redirect.uri',
                ],
                'expectedMessage' => 'Login config is incomplete. Required field(s) "[base_url]" missing.',
            ],

            'missing multiple: base_url, redirect_uri, icon' => [
                'array' => [
                    'snippet_key' => 'snippet.Key',
                    'class' => 'class',
                    'client_id' => 'C7i3ntID',
                    'client_secret' => 'c7i3ntS3cr3t',
                ],
                'expectedMessage' => 'Login config is incomplete. Required field(s) "[icon], [redirect_uri], [base_url]" missing.',
            ],

            'missing all' => [
                'array' => [],
                'expectedMessage' => 'Login config is incomplete. Required field(s) "[snippet_key], [icon], [class], [client_id], [client_secret], [redirect_uri], [base_url]" missing.',
            ],
        ];
    }
}
