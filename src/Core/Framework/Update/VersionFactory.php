<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update;

use Shopware\Core\Framework\Update\Struct\Version;

class VersionFactory
{
    private const TEST_VERSION_ARRAY = [
        'version' => '6.0.0-ea2',
        'release_date' => null,
        'security_update' => false,
        'uri' => 'https://releases.shopware.com/sw6/update_6.0.0_ea2_1571125323.zip',
        'size' => '10300647',
        'sha1' => '989a66605d12d347ceb727c73954bb0ba3b9192d',
        'sha256' => '8541ba418536bc84b1cd90063a3a41240646cbf83eef0fe809a0b02977e623c4',
        'checks' => [
            [
                'type' => 'writable',
                'value' => [
                    '/',
                ],
                'level' => 10,
            ],
            [
                'type' => 'phpversion',
                'value' => '7.4.0',
                'level' => 20,
            ],
            [
                'type' => 'mysqlversion',
                'value' => '5.7',
                'level' => 20,
            ],
            [
                'type' => 'licensecheck',
                'value' => [],
                'level' => 20,
            ],
        ],
        'changelog' => [
            'de' => [
                'id' => '240',
                'releaseId' => null,
                'language' => 'de',
                'changelog' => "<h2>Shopware Version test</h2>\n\nTestVersion\n",
                'release_id' => '126',
            ],
            'en' => [
                'id' => '241',
                'releaseId' => null,
                'language' => 'en',
                'changelog' => "<h2>Shopware Version test</h2>\n\nTestVersion\n",
                'release_id' => '126',
            ],
        ],
        'isNewer' => true,
    ];

    public static function create(array $data): Version
    {
        $version = new Version();
        $version->assign($data);

        return $version;
    }

    public static function createTestVersion(): Version
    {
        $version = new Version();
        $version->assign(self::TEST_VERSION_ARRAY);

        return $version;
    }
}
