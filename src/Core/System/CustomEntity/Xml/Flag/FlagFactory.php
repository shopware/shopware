<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Flag;

use Shopware\Core\System\CustomEntity\Xml\Flag\AdminUi\AdminUiFlag;
use Shopware\Core\System\CustomEntity\Xml\Flag\CmsAware\CmsAwareFlag;

/**
 * @internal
 */
class FlagFactory
{
    private const MAPPING = [
        'cms-aware' => CmsAwareFlag::class,
        'admin-ui' => AdminUiFlag::class,
    ];

    public static function createFromXml(\DOMElement $element): Flag
    {
        /** @var ?Flag $class */
        $class = self::MAPPING[$element->tagName] ?? null;

        if (!$class) {
            throw new \RuntimeException(sprintf('Flag type "%s" not found', $element->tagName));
        }

        return $class::fromXml($element);
    }
}
