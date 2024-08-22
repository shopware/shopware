<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Content\Cms\Exception\DuplicateCriteriaKeyException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('buyers-experience')]
class CmsException extends HttpException
{
    final public const DELETION_OF_DEFAULT_CODE = 'CONTENT__DELETION_DEFAULT_CMS_PAGE';

    final public const OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE = 'CONTENT__DELETION_OVERALL_DEFAULT_CMS_PAGE';

    final public const INVALID_FIELD_CONFIG_SOURCE_CODE = 'CONTENT__INVALID_FIELD_CONFIG_SOURCE';

    /**
     * @param array<string> $cmsPages
     */
    public static function deletionOfDefault(array $cmsPages): self
    {
        $pages = implode(', ', $cmsPages);

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DELETION_OF_DEFAULT_CODE,
            'The cms pages with ids "{{ pages }}" are assigned as a default and therefore can not be deleted.',
            ['pages' => $pages]
        );
    }

    public static function overallDefaultSystemConfigDeletion(string $cmsPageId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::OVERALL_DEFAULT_SYSTEM_CONFIG_DELETION_CODE,
            'The cms page with id "{{ cmsPageId }}" is assigned as a default to all sales channels and therefore can not be deleted.',
            ['cmsPageId' => $cmsPageId]
        );
    }

    public static function invalidFieldConfigSource(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_FIELD_CONFIG_SOURCE_CODE,
            'The source of the provided field config "{{ name }}" is invalid. It should be from type string.',
            ['name' => $name]
        );
    }

    public static function duplicateCriteriaKey(string $key): self
    {
        return new DuplicateCriteriaKeyException($key);
    }
}
