<?php declare(strict_types=1);

namespace Shopware\Core\Installer\Helper;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class InstallerRedirectHelper
{
    private const ALLOWED_PARAM_NAME_PATTERN = '/^[a-zA-Z0-9_\-]+$/';

    /**
     * @var array<string, string>
     */
    private array $allowedParameterRegexp = [
        'language' => '(?P<language>[a-z]{2})(?:-(?P<region>[A-Z]{2}))?',
        'ext_steps' => '1',
    ];

    /**
     * @var array<string, mixed>
     */
    private array $queryParameters = [];

    /**
     * @param array<string, mixed> $serverVariables - The $_SERVER superglobal
     */
    public function __construct(array $serverVariables)
    {
        if (isset($serverVariables['QUERY_STRING'])) {
            $this->sanitizeQueryString($serverVariables['QUERY_STRING']);
        }
    }

    /**
     * Build a sanitized query string
     */
    public function buildQueryString(): string
    {
        if ($this->queryParameters === []) {
            return '';
        }

        return '?' . http_build_query($this->queryParameters, '', '&', \PHP_QUERY_RFC3986);
    }

    private function sanitizeQueryString(string $queryString): void
    {
        if ($queryString === '') {
            return;
        }

        \parse_str($queryString, $params);

        foreach ($params as $key => $value) {
            if (!\array_key_exists($key, $this->allowedParameterRegexp)) {
                continue;
            }

            if (!\preg_match(self::ALLOWED_PARAM_NAME_PATTERN, $key)) {
                continue;
            }

            $parameterPattern = "/^{$this->allowedParameterRegexp[$key]}\$/";

            if (\is_array($value)) {
                $value = reset($value);
            }

            if (!\preg_match($parameterPattern, (string) $value)) {
                continue;
            }

            $this->queryParameters[$key] = $value;
        }
        \ksort($this->queryParameters);
    }
}
