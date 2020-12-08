<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Service;

/**
 * Generates a random unique Id and caches it in a local file.
 */
class UniqueIdGenerator
{
    /**
     * @var string
     */
    private $cacheFilePath;

    public function __construct(string $cacheFilePath)
    {
        $this->cacheFilePath = $cacheFilePath;
    }

    public function getUniqueId(): string
    {
        if (file_exists($this->cacheFilePath)) {
            return file_get_contents($this->cacheFilePath);
        }

        $uniqueId = $this->generateUniqueId();

        $this->saveUniqueId($uniqueId);

        return $uniqueId;
    }

    /**
     * @param int    $length
     * @param string $keyspace
     *
     * @return string
     */
    public function generateUniqueId($length = 32, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }

        return $str;
    }

    /**
     * @param string $uniqueId
     */
    private function saveUniqueId($uniqueId): void
    {
        file_put_contents($this->cacheFilePath, $uniqueId);
    }
}
