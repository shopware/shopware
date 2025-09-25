<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;

/**
 * Service for decoding compressed _criteria parameters.
 * Handles base64url decoding and gzip decompression of criteria data.
 */
#[Package('framework')]
class CompressedCriteriaDecoder
{
    private const MAX_GET_CRITERIA_LENGTH = 1024 * 128; // 128kb
    private const MAX_GET_CRITERIA_UNCOMPRESSED_LENGTH = self::MAX_GET_CRITERIA_LENGTH * 20; // using compression factor of 20 which is optimistic

    /**
     * Decode compressed criteria parameter from base64url(gzip(json_encode(criteria))) format
     *
     * @return array<string, mixed>
     */
    public function decode(string $encodedCriteria): array
    {
        try {
            // Hard limit to avoid overloading
            if (\strlen($encodedCriteria) > self::MAX_GET_CRITERIA_LENGTH) {
                throw DataAbstractionLayerException::invalidCriteriaParameter('The _criteria parameter is too long');
            }

            // Decode base64url
            $gzippedData = $this->base64urlDecode($encodedCriteria);
            if ($gzippedData === false) {
                throw DataAbstractionLayerException::invalidCriteriaParameter('Unable to decode base64 data');
            }

            // Decompress gzipped data
            // Limit the decompressed size for additional safety from malicious input.
            // Function throws a warning on failure, suppressing it as result is validated afterwards.
            $jsonData = @gzdecode($gzippedData, self::MAX_GET_CRITERIA_UNCOMPRESSED_LENGTH);

            if ($jsonData === false) {
                throw DataAbstractionLayerException::invalidCriteriaParameter('Unable to decompress gzipped data');
            }

            // Decode JSON
            $criteriaData = json_decode($jsonData, true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($criteriaData)) {
                throw DataAbstractionLayerException::invalidCriteriaParameter('Criteria data must be an array');
            }

            return $criteriaData;
        } catch (\JsonException $e) {
            throw DataAbstractionLayerException::invalidCriteriaParameter('Invalid JSON data: ' . $e->getMessage());
        } catch (DataAbstractionLayerException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw DataAbstractionLayerException::invalidCriteriaParameter('Unable to decode or decompress criteria data: ' . $e->getMessage());
        }
    }

    /**
     * The standard base64 alphabet contains + and / which are not URL safe.
     * Base64url encoding replaces + with - and / with _
     * (see RFC 4648, Section 5 https://datatracker.ietf.org/doc/html/rfc4648#section-5).
     * Padding restoration is unnecessary, as base64_decode handles it correctly.
     */
    private function base64urlDecode(string $data): string|false
    {
        return base64_decode(
            strtr($data, '-_', '+/'),
            true,
        );
    }
}
