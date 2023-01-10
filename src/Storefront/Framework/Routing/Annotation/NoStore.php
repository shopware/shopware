<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\Annotation;

use Shopware\Core\Framework\Routing\Annotation\BaseAnnotation;

/**
 * @package storefront
 *
 * @Annotation
 */
class NoStore extends BaseAnnotation
{
    public const ALIAS = 'noStore';

    public function getAliasName(): string
    {
        return self::ALIAS;
    }

    public function allowArray(): bool
    {
        return false;
    }
}
