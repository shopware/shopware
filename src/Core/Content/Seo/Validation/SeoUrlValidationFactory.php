<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Validation;

use Shopware\Core\System\Annotation\Concept\DeprecationPattern\RenameService;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;

/**
 * @RenameService(
 *     deprecatedService="SeoUrlValidationService",
 *     replacedBy="SeoUrlValidationFactory"
 * )
 * @Decoratable
 */
class SeoUrlValidationFactory extends SeoUrlValidationService
{
}
