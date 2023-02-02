<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\DataBag;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\ParameterBag;

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
     * @param string|null $key The name of the parameter to return or null to get them all
     *
     * @return array<string|int, mixed>
     */
    public function all(): array
    {
        $filterKey = \func_num_args() > 0 ? func_get_arg(0) : null;

        $data = $this->parameters;

        foreach ($data as $key => $value) {
            if ($value instanceof self) {
                $data[$key] = $value->all();
            }
        }

        if ($filterKey === null) {
            return $data;
        }

        if (!\is_array($data = $data[$filterKey] ?? [])) {
            throw new BadRequestException(sprintf('Unexpected value for parameter "%s": expecting "array", got "%s".', $filterKey, get_debug_type($data)));
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
        return new RequestDataBag(self::all());
    }
}
