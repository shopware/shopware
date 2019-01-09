<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use Shopware\Core\Framework\Struct\Uuid;

trait MediaFolderFixtures
{
    public function genFolders(array $customizations = [], ...$children): array
    {
        $folderConfig = $this->genMediaFolderConfig();
        $folder = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'emptyFolder',
            'useParentConfiguration' => false,
            'configuration' => $folderConfig,
        ];

        $folder = array_merge($folder, $customizations);

        $children = array_reduce($children, function ($acc, $val) {
            return array_merge($acc, $val);
        }, []);

        $payload = [];
        $payload[] = $folder;

        foreach ($children as $child) {
            if (!array_key_exists('parentId', $child)) {
                $child['parentId'] = $folder['id'];
            }

            if (array_key_exists('useParentConfiguration', $child) && $child['useParentConfiguration']) {
                $child['configurationId'] = $folder['configuration']['id'];
                unset($child['configuration']);
            }

            $payload[] = $child;
        }

        return $payload;
    }

    public function genMediaFolderConfig(array $customizations = []): array
    {
        $config = [
            'id' => Uuid::uuid4()->getHex(),
            'createThumbnails' => true,
            'keepAspectRatio' => true,
            'thumbnailQuality' => 80,
        ];

        return array_merge($config, $customizations);
    }
}
