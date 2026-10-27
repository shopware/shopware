<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * @internal
 */
#[Package('framework')]
final class ScopedConfigNormalizer
{
    public static function normalize(mixed $data): mixed
    {
        if ($data instanceof TaggedValue) {
            if ($data->getTag() !== RootSourceConfigMap::TAG) {
                // @phpstan-ignore shopware.domainException (caught and wrapped as a domain load-failed exception by the loader)
                throw new ParseException(\sprintf('Unsupported YAML tag "!%s".', $data->getTag()));
            }

            $value = $data->getValue();

            if (!\is_array($value)) {
                // @phpstan-ignore shopware.domainException (caught and wrapped as a domain load-failed exception by the loader)
                throw new ParseException(\sprintf('The "!%s" tag must wrap a map, got %s.', RootSourceConfigMap::TAG, get_debug_type($value)));
            }

            return [RootSourceConfigMap::MARKER => self::normalize($value)];
        }

        if (\is_array($data)) {
            if (\array_key_exists(RootSourceConfigMap::MARKER, $data)) {
                // @phpstan-ignore shopware.domainException (caught and wrapped as a domain load-failed exception by the loader)
                throw new ParseException(\sprintf('"%s" is a reserved key; use the "!%s" tag instead.', RootSourceConfigMap::MARKER, RootSourceConfigMap::TAG));
            }

            $normalized = [];

            foreach ($data as $key => $value) {
                $normalized[$key] = self::normalize($value);
            }

            return $normalized;
        }

        return $data;
    }
}
