<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct\Serializer;

use Symfony\Component\Serializer\Encoder\DecoderInterface;

class StructDecoder implements DecoderInterface
{
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

        if (array_key_exists('_class', $decoded) && preg_match('/(Collection|SearchResult)$/', $decoded['_class'])) {
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
