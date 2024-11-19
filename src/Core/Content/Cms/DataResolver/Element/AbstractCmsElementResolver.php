<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver\Element;

use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
abstract class AbstractCmsElementResolver implements CmsElementResolverInterface
{
    /**
     * @return mixed|Entity|Struct|null
     */
    protected function resolveEntityValue(?Entity $entity, string $path)
    {
        if ($entity === null) {
            return null;
        }

        $value = $entity;
        $entityPath = explode('.', $path);

        // if property does not exist, try to omit the first key as it may contain the entity name.
        // E.g. `product.description` does not exist, but will be found if the first part is omitted.
        $smartDetect = true;

        while (\count($entityPath) > 0) {
            $entityPathPart = array_shift($entityPath);

            if ($value === null) {
                break;
            }

            try {
                $parentValue = $value;
                switch (true) {
                    case \is_array($value):
                        $value = $value[$entityPathPart] ?? null;

                        break;
                    case $value instanceof Entity:
                        $value = $value->get($entityPathPart);

                        break;
                    case $value instanceof Struct:
                        $value = $value->getVars();
                        $value = $value[$entityPathPart] ?? null;

                        break;
                    default:
                        $value = null;
                }

                // On the last element, try to get the translation if nothing else was found
                if ($value === null && $parentValue instanceof Entity) {
                    $value = $parentValue->getTranslation($entityPathPart);
                }
            } catch (PropertyNotFoundException|\InvalidArgumentException $ex) {
                if (!$smartDetect) {
                    throw $ex;
                }
            }

            if ($value === null && !$smartDetect) {
                break;
            }

            $smartDetect = false;
        }

        return $value;
    }

    protected function resolveEntityValueToString(?Entity $entity, string $path, EntityResolverContext $resolverContext): string
    {
        $content = $this->resolveEntityValue($entity, $path);

        if ($content instanceof \DateTimeInterface) {
            $dateFormatter = new \IntlDateFormatter(
                $resolverContext->getRequest()->getLocale(),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::MEDIUM
            );
            $content = $dateFormatter->format($content);
        }

        if ($content === null || \is_scalar($content) || (\is_object($content) && \method_exists($content, '__toString'))) {
            return (string) $content;
        }

        return $path;
    }

    protected function resolveEntityValues(EntityResolverContext $resolverContext, string $content): ?string
    {
        // https://regex101.com/r/idIfbk/1
        return preg_replace_callback(
            '/{{\s*(?<property>[\w.\d]+)\s*}}/',
            function ($matches) use ($resolverContext) {
                try {
                    return $this->resolveEntityValueToString($resolverContext->getEntity(), $matches['property'], $resolverContext);
                } catch (PropertyNotFoundException|\InvalidArgumentException) {
                    return $matches[0];
                }
            },
            $content
        );
    }
}
