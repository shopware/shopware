<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Exception\MissingParameterException;
use Symfony\Component\HttpFoundation\Request;

class InternalRequest
{
    /**
     * @var array
     */
    protected $query = [];

    /**
     * @var array
     */
    protected $post = [];

    /**
     * @var array
     */
    protected $routing = [];

    public function __construct(array $query = [], array $post = [], array $routing = [])
    {
        $this->query = $query;
        $this->post = $post;
        $this->routing = $routing;
    }

    public static function createFromHttpRequest(Request $request): InternalRequest
    {
        return new self(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->get('_route_params')
        );
    }

    public function optional(string $key, $default = null)
    {
        try {
            return $this->_get($key, $this->routing);
        } catch (MissingParameterException $e) {
        }

        try {
            return $this->_get($key, $this->query);
        } catch (MissingParameterException $e) {
        }

        try {
            return $this->_get($key, $this->post);
        } catch (MissingParameterException $e) {
        }

        return $default;
    }

    public function requirePost(string $key)
    {
        return $this->_get($key, $this->post);
    }

    public function requireGet(string $key)
    {
        try {
            return $this->_get($key, $this->query);
        } catch (MissingParameterException $e) {
        }

        return $this->_get($key, $this->routing);
    }

    public function requireRouting(string $key)
    {
        return $this->_get($key, $this->routing);
    }

    public function optionalGet(string $key, $default = null)
    {
        try {
            return $this->_get($key, $this->query);
        } catch (MissingParameterException $e) {
        }

        try {
            return $this->_get($key, $this->routing);
        } catch (MissingParameterException $e) {
        }

        return $default;
    }

    public function optionalPost(string $key, $default = null)
    {
        try {
            return $this->_get($key, $this->post);
        } catch (MissingParameterException $e) {
        }

        return $default;
    }

    public function optionalRouting(string $key, $default = null)
    {
        try {
            return $this->_get($key, $this->routing);
        } catch (MissingParameterException $e) {
        }

        return $default;
    }

    /**
     * @param string $key
     *
     * @throws MissingParameterException
     *
     * @return mixed
     */
    public function require(string $key)
    {
        try {
            return $this->_get($key, $this->routing);
        } catch (MissingParameterException $e) {
        }

        try {
            return $this->_get($key, $this->query);
        } catch (MissingParameterException $e) {
        }

        return $this->_get($key, $this->post);
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getPost(): array
    {
        return $this->post;
    }

    public function getRouting(): array
    {
        return $this->routing;
    }

    /**
     * @param string $key
     * @param array  $values
     *
     * @throws MissingParameterException
     *
     * @return array|mixed
     */
    private function _get(string $key, array $values)
    {
        //direct hit
        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        //explode key for nested array access
        $parts = explode('.', $key);

        $cursor = $values;

        while ($parts) {
            $part = array_shift($parts);

            if (!array_key_exists($part, $cursor)) {
                throw new MissingParameterException($part);
            }

            $cursor = $cursor[$part];
        }

        return $cursor;
    }
}
