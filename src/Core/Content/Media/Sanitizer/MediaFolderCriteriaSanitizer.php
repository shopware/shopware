<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Sanitizer;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class MediaFolderCriteriaSanitizer extends AbstractCriteriaSanitizer
{
    public function shouldSanitizeField(string $fieldPath): bool
    {
        return str_ends_with($fieldPath, 'media_folder.configuration.private');
    }

    public function shouldSanitizeValue(mixed $value): bool
    {
        if (($value ?? false) === false) {
            return false;
        }

        return true;
    }

    public function getFilterReplacement(): MultiFilter
    {
        return new MultiFilter('OR', [
            new EqualsFilter('media_folder.configuration.private', false),
            new EqualsFilter('media_folder.configuration.private', null),
        ]);
    }
}
