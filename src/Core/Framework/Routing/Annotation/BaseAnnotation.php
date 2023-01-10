<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Annotation;

/**
 * @internal
 */
abstract class BaseAnnotation
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values)
    {
        foreach ($values as $k => $v) {
            if (!method_exists($this, $name = 'set' . $k)) {
                throw new \RuntimeException(sprintf('Unknown key "%s" for annotation "@%s".', $k, static::class));
            }

            $this->$name($v);
        }
    }
}
