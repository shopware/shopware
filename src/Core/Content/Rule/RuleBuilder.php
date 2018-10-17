<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Symfony\Component\Serializer\SerializerInterface;

class RuleBuilder
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function isRule(array $payload): bool
    {
        try {
            $this->buildRule($payload);
            $payload = json_encode($payload);

            $this->serializer->deserialize($payload, '', 'json');

            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    private function buildRule(&$payload)
    {
        if (array_key_exists('ruleType', $payload)) {
            $payload['_class'] = $payload['ruleType'];
            unset($payload['ruleType']);
        }
        foreach ($payload as $key => &$value) {
            if (!is_array($value)) {
                continue;
            }

            $this->buildRule($value);
        }
    }
}