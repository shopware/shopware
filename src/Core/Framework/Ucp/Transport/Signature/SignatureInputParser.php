<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Signature;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Parses a `Signature-Input` header value per RFC 8941 (Structured Field
 * Dictionary) + RFC 9421.
 *
 * Example input:
 *
 *   sig1=("@method" "@target-uri" "content-digest");created=1730000000;keyid="ucp_2026_abc";alg="ecdsa-p256-sha256"
 *
 * @internal
 */
#[Package('framework')]
class SignatureInputParser
{
    /**
     * @return array<string, array{value: string, components: SignatureComponents}>
     */
    public function parse(string $header): array
    {
        $result = [];

        // Naive but precise: parse label by label.
        $offset = 0;
        $length = \strlen($header);

        while ($offset < $length) {
            // Skip whitespace and commas
            while ($offset < $length && (ctype_space($header[$offset]) || $header[$offset] === ',')) {
                ++$offset;
            }
            if ($offset >= $length) {
                break;
            }

            $labelStart = $offset;
            while ($offset < $length && $header[$offset] !== '=') {
                ++$offset;
            }
            if ($offset >= $length) {
                throw UcpException::signatureInvalid('Malformed Signature-Input: missing "=" after label');
            }

            $label = trim(substr($header, $labelStart, $offset - $labelStart));
            ++$offset;

            // Expect "("
            if ($offset >= $length || $header[$offset] !== '(') {
                throw UcpException::signatureInvalid('Malformed Signature-Input: expected "(" after label');
            }
            ++$offset;

            $components = [];
            while ($offset < $length && $header[$offset] !== ')') {
                while ($offset < $length && ctype_space($header[$offset])) {
                    ++$offset;
                }
                if ($offset >= $length || $header[$offset] === ')') {
                    break;
                }
                if ($header[$offset] !== '"') {
                    throw UcpException::signatureInvalid('Malformed Signature-Input: expected quoted component identifier');
                }
                ++$offset;
                $compStart = $offset;
                while ($offset < $length && $header[$offset] !== '"') {
                    ++$offset;
                }
                if ($offset >= $length) {
                    throw UcpException::signatureInvalid('Malformed Signature-Input: unterminated component identifier');
                }
                $components[] = substr($header, $compStart, $offset - $compStart);
                ++$offset;
            }
            if ($offset >= $length || $header[$offset] !== ')') {
                throw UcpException::signatureInvalid('Malformed Signature-Input: unterminated component list');
            }
            $componentListEnd = $offset + 1;
            ++$offset;

            // Now parameters (`;name=value` …)
            $parameters = [];
            while ($offset < $length && $header[$offset] === ';') {
                ++$offset;
                $paramStart = $offset;
                while ($offset < $length && $header[$offset] !== '=') {
                    ++$offset;
                }
                if ($offset >= $length) {
                    throw UcpException::signatureInvalid('Malformed Signature-Input: missing "=" in parameter');
                }
                $paramName = trim(substr($header, $paramStart, $offset - $paramStart));
                ++$offset;

                // Value: quoted string or unquoted token
                $valueStart = $offset;
                if ($offset < $length && $header[$offset] === '"') {
                    ++$offset;
                    $valueStart = $offset;
                    while ($offset < $length && $header[$offset] !== '"') {
                        ++$offset;
                    }
                    $value = substr($header, $valueStart, $offset - $valueStart);
                    ++$offset;
                } else {
                    while ($offset < $length && $header[$offset] !== ';' && $header[$offset] !== ',') {
                        ++$offset;
                    }
                    $value = trim(substr($header, $valueStart, $offset - $valueStart));
                }
                $parameters[$paramName] = $value;
            }

            // Reconstruct the verbatim component list + parameters for signature-base building
            $signatureParamsValue = '(' . implode(' ', array_map(static fn (string $c) => '"' . $c . '"', $components)) . ')';
            foreach ($parameters as $name => $value) {
                $signatureParamsValue .= ';' . $name . '=';
                $signatureParamsValue .= ctype_digit($value)
                    ? $value
                    : '"' . $value . '"';
            }

            $result[$label] = [
                'value' => $signatureParamsValue,
                'components' => new SignatureComponents($components, $parameters),
            ];
        }

        return $result;
    }
}
