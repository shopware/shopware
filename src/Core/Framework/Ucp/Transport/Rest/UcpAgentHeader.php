<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Rest;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Parser for the UCP-Agent HTTP header, which uses RFC 8941 Dictionary
 * Structured Field syntax.
 *
 * Example:
 *
 *   UCP-Agent: profile="https://agent.example/profiles/shopping-agent.json"
 *
 * @internal
 */
#[Package('framework')]
final class UcpAgentHeader
{
    public const HEADER_NAME = 'ucp-agent';

    public function __construct(
        public readonly string $profileUri,
        /**
         * @var array<string, string>
         */
        public readonly array $additionalParameters,
    ) {
    }

    public static function parse(string $headerValue): self
    {
        $entries = self::parseDictionary($headerValue);
        $profile = $entries['profile'] ?? null;

        if (!\is_string($profile) || $profile === '') {
            throw UcpException::invalidProfileUrl('(missing profile parameter)');
        }

        unset($entries['profile']);

        return new self($profile, $entries);
    }

    /**
     * @return array<string, string>
     */
    private static function parseDictionary(string $value): array
    {
        $entries = [];
        foreach (preg_split('/\s*[;,]\s*/', $value) ?: [] as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $eq = strpos($segment, '=');
            if ($eq === false) {
                $entries[strtolower($segment)] = '';
                continue;
            }
            $key = strtolower(trim(substr($segment, 0, $eq)));
            $raw = trim(substr($segment, $eq + 1));
            if (str_starts_with($raw, '"') && str_ends_with($raw, '"')) {
                $raw = substr($raw, 1, -1);
            }
            $entries[$key] = $raw;
        }

        return $entries;
    }
}
