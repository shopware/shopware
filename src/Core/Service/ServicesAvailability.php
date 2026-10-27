<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ServicesAvailability implements \JsonSerializable
{
    public const AUTO = 'auto';

    public function __construct(
        public string $setting,
        private string $appEnv,
    ) {
    }

    public function available(): bool
    {
        if ($this->setting === self::AUTO) {
            return $this->appEnv === 'prod';
        }

        return filter_var($this->setting, \FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{enabled: bool, setting: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'enabled' => $this->available(),
            'setting' => $this->setting,
        ];
    }
}
