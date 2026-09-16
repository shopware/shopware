<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * Single source of truth for deriving the `descriptionTeaser` from a product description: the
 * description is stripped of HTML via the configurable html_sanitizer (field key
 * `product_translation.descriptionTeaser`, by default removing all tags) and then truncated.
 *
 * @internal
 */
#[Package('inventory')]
class ProductDescriptionTeaserBuilder
{
    public const TEASER_FIELD = 'product_translation.descriptionTeaser';

    private const MAX_LENGTH = 512;

    public function __construct(private readonly HtmlSanitizer $sanitizer)
    {
    }

    public function build(?string $description): ?string
    {
        if ($description === null || $description === '') {
            return $description;
        }

        $stripped = $this->sanitizer->sanitize($description, [], false, self::TEASER_FIELD);

        return mb_substr($stripped, 0, self::MAX_LENGTH);
    }
}
