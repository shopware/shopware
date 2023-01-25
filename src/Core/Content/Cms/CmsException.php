<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('content')]
class CmsException extends HttpException
{
    final public const DELETION_OF_DEFAULT_CODE = 'CONTENT__DELETION_DEFAULT_CMS_PAGE';
    final public const OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE = 'CONTENT__DELETION_OVERALL_DEFAULT_CMS_PAGE';

    /**
     * @param array<string> $cmsPages
     */
    public static function deletionOfDefault(array $cmsPages): self
    {
        $pages = implode(', ', $cmsPages);

        return new self(
            Response::HTTP_CONFLICT,
            self::DELETION_OF_DEFAULT_CODE,
            'The cms pages with ids "{{ pages }}" are assigned as a default and therefore can not be deleted.',
            ['pages' => $pages]
        );
    }

    public static function overallDefaultSystemConfigDeletion(string $cmsPageId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE,
            'The cms page with id "{{ cmsPageId }}" is assigned as a default to all sales channels and therefore can not be deleted.',
            ['cmsPageId' => $cmsPageId]
        );
    }
}
