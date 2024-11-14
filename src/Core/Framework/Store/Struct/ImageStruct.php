<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ImageStruct extends StoreStruct
{
    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $remoteLink;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $raw;

    /**
     * @return ImageStruct
     */
    public static function fromArray(array $data): StoreStruct
    {
        return (new self())->assign($data);
    }

    public function getRemoteLink(): string
    {
        return $this->remoteLink;
    }

    public function getRaw(): ?string
    {
        return $this->raw;
    }

    public function setRaw(?string $raw): void
    {
        $this->raw = $raw;
    }
}
