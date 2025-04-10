<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payload;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @phpstan-type RequestOptions array{'app_request_context': Context, 'request_type': array{'app_secret': non-falsy-string, 'validated_response': true}, 'headers': array{Content-Type: string}, 'body': string, 'timeout'?: int}
 */
#[Package('checkout')]
class AppPayloadStruct
{
    use JsonSerializableTrait {
        jsonSerialize as private traitJsonSerialize;
    }

    public readonly Context $appRequestContext;

    /**
     * @var array{'app_secret': non-falsy-string, 'validated_response': true}
     */
    public readonly array $requestType;

    /**
     * @var array{Content-Type: string}
     */
    public readonly array $headers;

    public readonly string $body;

    public readonly ?int $timeout;

    /**
     * @param RequestOptions $data
     */
    public function __construct(array $data)
    {
        $this->appRequestContext = $data['app_request_context'];
        $this->requestType = $data['request_type'];
        $this->headers = $data['headers'];
        $this->body = $data['body'];
        $this->timeout = $data['timeout'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $vars = $this->traitJsonSerialize();
        $converter = new CamelCaseToSnakeCaseNameConverter();

        $snakeCaseKeys = array_map(function (string $key) use ($converter) {
            return $converter->normalize($key);
        }, array_keys($vars));

        return array_combine($snakeCaseKeys, $vars);
    }
}
