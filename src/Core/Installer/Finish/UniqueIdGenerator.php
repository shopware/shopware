<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Finish;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class UniqueIdGenerator
{
    private readonly string $cacheFilePath;

    public function __construct(string $projectDir)
    {
        $this->cacheFilePath = $projectDir . '/.uniqueid.txt';
    }

    public function getUniqueId(): string
    {
        if (file_exists($this->cacheFilePath)) {
            if ($id = file_get_contents($this->cacheFilePath)) {
                return $id;
            }
        }

        $uniqueId = $this->generateUniqueId();

        $this->saveUniqueId($uniqueId);

        return $uniqueId;
    }

    private function generateUniqueId(): string
    {
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < 32; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }

        return $str;
    }

    private function saveUniqueId(string $uniqueId): void
    {
        file_put_contents($this->cacheFilePath, $uniqueId);
    }
}
