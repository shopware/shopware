<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

/**
 * @internal We might break this in v6.2
 */
class SupportedFeaturesService
{
    /**
     * @var array|string[]
     */
    private $entities;

    /**
     * @var array|string[]
     */
    private $fileTypes;

    public function __construct(iterable $entities, iterable $fileTypes)
    {
        $this->entities = [];
        foreach ($entities as $entityName) {
            if (!is_string($entityName)) {
                throw new \InvalidArgumentException(sprintf(
                    'Supported entities should be collection of strings. %s given.',
                    gettype($entityName)
                ));
            }
            $this->entities[] = $entityName;
        }

        $this->fileTypes = [];
        foreach ($fileTypes as $fileType) {
            if (!is_string($fileType)) {
                throw new \InvalidArgumentException(sprintf(
                    'Supported file types should be collection of strings. %s given',
                    gettype($fileType)
                ));
            }
            $this->fileTypes[] = $fileType;
        }
    }

    public function getEntities(): array
    {
        return $this->entities;
    }

    public function getFileTypes(): array
    {
        return $this->fileTypes;
    }

    public function getUploadFileSizeLimit(): int
    {
        $twoGiB = 2 * 1024 * 1024 * 1024;
        $values = [
            self::toBytes(ini_get('upload_max_filesize')),
            self::toBytes(ini_get('post_max_size')),
            $twoGiB, // 2 GiB as fallback, because file size is stored in MySQL INT column
        ];

        $limits = array_filter($values, static function (int $value) {
            return $value > 0;
        });

        $min = min(...$limits);
        if ($min === false) {
            return $twoGiB;
        }

        return $min;
    }

    private static function toBytes(string $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        $length = mb_strlen($value);
        $qty = (int) mb_substr($value, 0, $length - 1);
        $unit = mb_strtolower(mb_substr($value, $length - 1));
        switch ($unit) {
            case 'k':
                $qty *= 1024;

                break;
            case 'm':
                $qty *= 1048576;

                break;
            case 'g':
                $qty *= 1073741824;

                break;
        }

        return $qty;
    }
}
