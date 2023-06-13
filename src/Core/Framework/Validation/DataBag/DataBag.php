<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\DataBag;

use Shopware\Core\Framework\Feature;
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
        $parameters = $this->wrapArrayParameters($parameters);

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

    public function add(array $parameters = []): void
    {
        /**
         * @deprecated tag:v6.6.0 - remove complete if statement, parameters will always be translated to databags
         */
        if (Feature::has('v6.6.0.0')) {
            $parameters = $this->wrapArrayParameters($parameters);
        }

        parent::add($parameters);
    }

    public function set(string $key, $value): void
    {
        /**
         * @deprecated tag:v6.6.0 - remove complete if statement, parameters will always be translated to databags
         */
        if (Feature::has('v6.6.0.0')) {
            $value = $this->wrapArrayParameters([$value])[0];
        }

        parent::set($key, $value);
    }

    public function __clone(): void
    {
        foreach ($this->parameters as &$value) {
            if ($value instanceof self) {
                $value = clone $value;
            }
        }
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

    private function wrapArrayParameters(array $parameters): array
    {
        foreach ($parameters as $key => $value) {
            if (\is_array($value)) {
                $parameters[$key] = new static($value);
            }
        }

        return $parameters;
    }
}
