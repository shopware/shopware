<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Signature;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Builds the "signature base" string per RFC 9421 §2.5.
 *
 * Format (for each component, separated by \n):
 *
 *   "<lower-case-component-name>": <value>
 *
 * followed by a final line:
 *
 *   "@signature-params": <parameter-list>
 *
 * The parameter-list is the verbatim value of the `Signature-Input` header for
 * the signature label (including the leading `(`).
 *
 * @internal
 */
#[Package('framework')]
class SignatureBase
{
    /**
     * @param array<string, string> $headers
     */
    public function buildForRequest(
        string $method,
        string $targetUri,
        array $headers,
        SignatureComponents $components,
        string $signatureParamsValue
    ): string {
        $lines = [];

        foreach ($components->components as $name) {
            $lines[] = '"' . $name . '": ' . $this->resolveComponentValue($name, $method, $targetUri, $headers);
        }

        $lines[] = '"@signature-params": ' . $signatureParamsValue;

        return implode("\n", $lines);
    }

    /**
     * @param array<string, string> $headers
     */
    public function buildForResponse(
        int $statusCode,
        array $headers,
        SignatureComponents $components,
        string $signatureParamsValue
    ): string {
        $lines = [];

        foreach ($components->components as $name) {
            $lines[] = '"' . $name . '": ' . $this->resolveComponentValueForResponse($name, $statusCode, $headers);
        }

        $lines[] = '"@signature-params": ' . $signatureParamsValue;

        return implode("\n", $lines);
    }

    public function buildFromSymfonyRequest(
        Request $request,
        SignatureComponents $components,
        string $signatureParamsValue
    ): string {
        return $this->buildForRequest(
            $request->getMethod(),
            $request->getUri(),
            self::normalizeHeaders($request->headers->all()),
            $components,
            $signatureParamsValue
        );
    }

    /**
     * @param array<string, list<string|null>|string> $headers
     *
     * @return array<string, string>
     */
    public static function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $key = strtolower((string) $name);
            $out[$key] = \is_array($value) ? implode(', ', array_map(static fn ($v): string => (string) $v, $value)) : (string) $value;
        }

        return $out;
    }

    /**
     * @param array<string, string> $headers
     */
    private function resolveComponentValue(string $name, string $method, string $targetUri, array $headers): string
    {
        return match ($name) {
            '@method' => strtoupper($method),
            '@target-uri' => $targetUri,
            '@authority' => (string) (parse_url($targetUri, \PHP_URL_HOST) ?? ''),
            '@scheme' => (string) (parse_url($targetUri, \PHP_URL_SCHEME) ?? ''),
            '@path' => (string) (parse_url($targetUri, \PHP_URL_PATH) ?? '/'),
            '@query' => '?' . (string) (parse_url($targetUri, \PHP_URL_QUERY) ?? ''),
            default => $this->resolveHeaderValue($name, $headers),
        };
    }

    /**
     * @param array<string, string> $headers
     */
    private function resolveComponentValueForResponse(string $name, int $statusCode, array $headers): string
    {
        return match ($name) {
            '@status' => (string) $statusCode,
            default => $this->resolveHeaderValue($name, $headers),
        };
    }

    /**
     * @param array<string, string> $headers
     */
    private function resolveHeaderValue(string $name, array $headers): string
    {
        $key = strtolower($name);
        if (!isset($headers[$key])) {
            throw UcpException::signatureInvalid(\sprintf('Signed header "%s" missing from request', $name));
        }

        return trim($headers[$key]);
    }
}
