<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Facade;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * The `request` service allows you to access the current request in the script
 *
 * Examples:
 * {% raw %}
 * ```twig
 * {% block response %}
 *  {% if services.request.method != "POST" %}
 *      {% set response = services.response.json({
 *          'error': 'Only POST requests are allowed',
 *      }, 405) %}
 *      {% do hook.setResponse(response) %}
 *      {% return %}
 *  {% endif %}
 *
 *  {% set response = services.response.json(services.request.request) %}
 *  {% do hook.setResponse(response) %}
 * {% endblock %}
 * ```
 * {% endraw %}
 *
 * @script-service miscellaneous
 *
 * @example scripts/store-api-request-test/store-api-request-test.twig Use request to determine method and return all json body back
 */
#[Package('core')]
final class RequestFacade
{
    private const ALLOWED_PARAMETERS = [
        'content-type',
        'content-length',
        'accept',
        'accept-language',
        'user-agent',
        'referer',
    ];

    /**
     * @internal
     */
    public function __construct(private readonly Request $request)
    {
    }

    /**
     * The ip method returns the real client ip address
     *
     * @return string|null request client ip address
     */
    public function ip(): ?string
    {
        return $this->request->getClientIp();
    }

    /**
     * The scheme method returns the request scheme
     *
     * @return string request scheme
     */
    public function scheme(): string
    {
        return $this->request->getScheme();
    }

    /**
     * The method returns the request method in upper case
     *
     * @return string request method in upper case
     */
    public function method(): string
    {
        return $this->request->getMethod();
    }

    /**
     * The method `uri` returns the request uri with the resolved url
     *
     * @return string request uri
     */
    public function uri(): string
    {
        return $this->request->attributes->get('sw-original-request-uri', $this->request->getRequestUri());
    }

    /**
     * The method `pathInfo` returns the request path info. The path info can be also an internal link when a seo url is used.
     *
     * @return string request path info
     */
    public function pathInfo(): string
    {
        return $this->request->getPathInfo();
    }

    /**
     * The method `query` returns all query parameters as an array
     *
     * @return array<string, mixed> request query parameters
     */
    public function query(): array
    {
        return $this->request->query->all();
    }

    /**
     * The method `request` returns all post parameters as an array.
     * On `application/json` requests this contains also the json body parsed as an array.
     *
     * @return array<string, mixed> request post parameters
     */
    public function request(): array
    {
        return $this->request->request->all();
    }

    /**
     * The method `headers` returns all request headers as an array.
     * It is possible to access only the following headers: content-type, content-length, accept, accept-language, user-agent, referer
     *
     * @return array<string, array<int, string|null>|string|null> request headers
     */
    public function headers(): array
    {
        $headers = array_change_key_case($this->request->headers->all());

        return array_intersect_key($headers, array_flip(self::ALLOWED_PARAMETERS));
    }

    /**
     * The method `cookies` returns all request cookies as an array.
     *
     * @return array<string, array<mixed>|bool|float|int|string> request cookies
     */
    public function cookies(): array
    {
        return $this->request->cookies->all();
    }
}
