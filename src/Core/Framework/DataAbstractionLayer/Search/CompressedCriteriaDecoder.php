<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Base64;
use Shopware\Core\Framework\Util\Exception\Base64DecodingException;

/**
 * @internal
 */
#[Package('framework')]
class CompressedCriteriaDecoder
{
    /**
     * Using 20x multiplier as an optimistic compression rate avoid decompression bombs.
     */
    private const DEFAULT_DECOMPRESSED_CRITERIA_LENGTH_LIMIT = self::DEFAULT_COMPRESSED_CRITERIA_LENGTH_LIMIT * 20;

    /**
     * Setting default to 128kb, which should be a reasonable limit for parameters provided via URL.
     */
    private const DEFAULT_COMPRESSED_CRITERIA_LENGTH_LIMIT = 1024 * 128;

    /**
     * @internal
     */
    public function __construct(
        /**
         * Limit for the maximum allowed length of the compressed criteria string.
         */
        private readonly int $compressedCriteriaLengthLimit = self::DEFAULT_COMPRESSED_CRITERIA_LENGTH_LIMIT,

        /**
         * Limit for the maximum allowed length of the decompressed criteria string to avoid decompression bombs.
         */
        private readonly int $decompressedCriteriaLengthLimit = self::DEFAULT_DECOMPRESSED_CRITERIA_LENGTH_LIMIT,
    ) {
    }

    /**
     * Decodes and decompresses the given criteria string formed through: json_encode -> gzip -> base64url_encode
     *
     * @throws DataAbstractionLayerException
     *
     * @return array<string, mixed>
     */
    public function decode(string $encodedCriteria): array
    {
        // Hard limit to avoid overloading
        if (\strlen($encodedCriteria) > $this->compressedCriteriaLengthLimit) {
            throw DataAbstractionLayerException::invalidCompressedCriteriaParameter('The _criteria parameter is too long');
        }

        // Decode base64url
        try {
            $gzippedData = Base64::urlDecode($encodedCriteria);
        } catch (Base64DecodingException $e) {
            throw DataAbstractionLayerException::invalidCompressedCriteriaParameter($e->getMessage());
        }

        // Decompress gzipped data
        // Limit the decompressed size for additional safety from malicious input.
        // Function throws a warning on failure, suppressing it as result is validated afterward.
        $jsonData = @gzdecode($gzippedData, $this->decompressedCriteriaLengthLimit);

        if ($jsonData === false) {
            throw DataAbstractionLayerException::invalidCompressedCriteriaParameter('Unable to decompress gzipped data');
        }

        // Decode JSON
        try {
            $criteriaData = json_decode($jsonData, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw DataAbstractionLayerException::invalidCompressedCriteriaParameter('Invalid JSON data: ' . $e->getMessage());
        }

        if (!\is_array($criteriaData)) {
            throw DataAbstractionLayerException::invalidCompressedCriteriaParameter('Criteria data must be an array');
        }

        return $criteriaData;
    }
}
