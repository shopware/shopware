<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin;

use Shopware\Core\Framework\Log\Package;

/**
 * Marks a media file as made or changed with generative AI.
 * The two cases are the two AI terms of the IPTC Digital Source Type vocabulary,
 * which C2PA uses as well.
 * A media without an AI signal has no case (`null`).
 *
 * @see https://cv.iptc.org/newscodes/digitalsourcetype/
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
enum MediaAiOrigin: string
{
    /**
     * The whole image was created by a generative AI model.
     * IPTC: `trainedAlgorithmicMedia` ("Created using Generative AI").
     */
    case Generated = 'ai_generated';

    /**
     * An existing image was changed with generative AI, or AI-generated parts were combined with other content.
     * IPTC: `compositeWithTrainedAlgorithmicMedia` ("Edited using Generative AI")
     * and `compositeSynthetic` ("Composite including generative AI elements").
     */
    case Modified = 'ai_modified';

    private const DIGITAL_SOURCE_TYPE_NAMESPACES = [
        'http://cv.iptc.org/newscodes/digitalsourcetype/',
        'https://cv.iptc.org/newscodes/digitalsourcetype/',
    ];

    /**
     * Maps an IPTC Digital Source Type to an AI origin.
     * Accepts the full term URI and the bare term code, because some tools write only the code.
     * Returns `null` for all terms that do not involve generative AI,
     * including `algorithmicMedia`, which IPTC defines as made by an algorithm without training data.
     */
    public static function fromDigitalSourceType(string $digitalSourceType): ?self
    {
        $term = trim($digitalSourceType);

        foreach (self::DIGITAL_SOURCE_TYPE_NAMESPACES as $namespace) {
            if (str_starts_with(strtolower($term), $namespace)) {
                $term = substr($term, \strlen($namespace));

                break;
            }
        }

        // Google Merchant Center documents capitalised spellings, for example "TrainedAlgorithmicMedia"
        return match (strtolower($term)) {
            'trainedalgorithmicmedia' => self::Generated,
            'compositewithtrainedalgorithmicmedia', 'compositesynthetic' => self::Modified,
            default => null,
        };
    }
}
