<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;

#[Package('system-settings')]
class ImportExportFileEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $originalName;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var \DateTimeInterface
     */
    protected $expireDate;

    /**
     * @var int
     */
    protected $size;

    /**
     * @var ImportExportLogEntity|null
     */
    protected $log;

    /**
     * @var string|null
     */
    protected $accessToken;

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): void
    {
        $this->originalName = $originalName;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getExpireDate(): \DateTimeInterface
    {
        return $this->expireDate;
    }

    public function setExpireDate(\DateTimeInterface $expireDate): void
    {
        $this->expireDate = $expireDate;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function getLog(): ?ImportExportLogEntity
    {
        return $this->log;
    }

    public function setLog(ImportExportLogEntity $log): void
    {
        $this->log = $log;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public static function generateAccessToken(): string
    {
        return Random::getBase64UrlString(32);
    }

    public static function buildPath(string $id): string
    {
        return implode('/', str_split($id, 8));
    }
}
