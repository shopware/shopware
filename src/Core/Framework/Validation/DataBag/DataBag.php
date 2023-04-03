<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\DataBag;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\ParameterBag;

#[Package('core')]
class DataBag extends ParameterBag
{
    /**
     * @param array<string|int, mixed> $parameters
     */
    final public function __construct(array $parameters = [])
    {
        foreach ($parameters as $key => $value) {
            if (\is_array($value)) {
                $parameters[$key] = new static($value);
            }
        }

        parent::__construct($parameters);
    }

    /**
     * @return array<string|int, mixed>
     */
    public function all(?string $key = null): array
    {
        $data = $this->parameters;

        foreach ($data as $k => $value) {
            if ($value instanceof self) {
                $data[$k] = $value->all();
            }
        }

        if ($key === null) {
            return $data;
        }

        if (!\is_array($data = $data[$key] ?? [])) {
            throw new BadRequestException(sprintf('Unexpected value for parameter "%s": expecting "array", got "%s".', $key, get_debug_type($data)));
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function only(string ...$keys): array
    {
        return array_intersect_key($this->parameters, array_flip($keys));
    }

    public function toRequestDataBag(): RequestDataBag
    {
        return new RequestDataBag($this->all());
    }
}
