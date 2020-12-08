<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\DataBag;

use Symfony\Component\HttpFoundation\ParameterBag;

class DataBag extends ParameterBag
{
    final public function __construct(array $parameters = [])
    {
        foreach ($parameters as $key => $value) {
            if (\is_array($value)) {
                $parameters[$key] = new static($value);
            }
        }

        parent::__construct($parameters);
    }

    public function all(): array
    {
        $data = $this->parameters;

        foreach ($data as $key => $value) {
            if ($value instanceof self) {
                $data[$key] = $value->all();
            }
        }

        return $data;
    }

    public function only(string ...$keys): array
    {
        return array_intersect_key($this->parameters, array_flip($keys));
    }

    public function toRequestDataBag(): RequestDataBag
    {
        return new RequestDataBag(self::all());
    }
}
