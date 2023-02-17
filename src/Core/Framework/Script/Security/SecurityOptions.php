<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Security;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class SecurityOptions
{
    public const CSP_HEADER = 'Content-Security-Policy';

    public const FRAME_OPTIONS_HEADER = 'X-Frame-Options';

    public const ALLOWED_OPTIONS = [
        self::CSP_HEADER,
        self::FRAME_OPTIONS_HEADER,
    ];

    private array $options = [];

    public function setOption(string $name, ?string $value): void
    {
        if (!\in_array($name, self::ALLOWED_OPTIONS, true)) {
            throw new \RunTimeException(sprintf('Security option "%s" is not allowed', $name));
        }
        if (isset($value)) {
            $this->options[$name] = $value;
        } else {
            unset($this->options[$name]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
