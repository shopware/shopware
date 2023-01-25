<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct\Serializer;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

#[Package('core')]
class StructDecoder implements DecoderInterface
{
    /**
     * @return array|mixed
     */
    public function decode($data, $format, array $context = [])
    {
        return $this->format($data);
    }

    public function supportsDecoding($format): bool
    {
        return $format === 'struct';
    }

    private function format($decoded)
    {
        if (!\is_array($decoded) || empty($decoded)) {
            return $decoded;
        }

        if (\array_key_exists('_class', $decoded) && preg_match('/(Collection|SearchResult)$/', (string) $decoded['_class'])) {
            $elements = [];
            foreach ($decoded['elements'] as $element) {
                $elements[] = $this->format($element);
            }

            return $elements;
        }

        unset($decoded['_class']);

        foreach ($decoded as $key => $value) {
            $decoded[$key] = $this->format($value);
        }

        return $decoded;
    }
}
