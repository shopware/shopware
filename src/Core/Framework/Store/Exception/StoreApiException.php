<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use GuzzleHttp\Exception\ClientException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class StoreApiException extends ShopwareHttpException
{
    /**
     * @var string
     */
    protected $code = 'STORE-API';

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $documentationLink;

    /**
     * @var int
     */
    private $statusCode;

    public function __construct(ClientException $exception)
    {
        $data = json_decode($exception->getResponse()->getBody()->getContents(), true);
        parent::__construct($data['description']);

        $this->title = $data['title'];
        $this->documentationLink = $data['documentationLink'];
        $this->statusCode = $data['status'];
    }

    public function getStatusCode(): int
    {
        //TODO: Responses with 401 code have no body with axios
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        $error = [
            'code' => $this->code,
            'status' => (string) $this->getStatusCode(),
            'title' => $this->title,
            'detail' => $this->getMessage(),
            'meta' => [
                'documentationLink' => $this->documentationLink,
            ],
        ];

        if ($withTrace) {
            $error['trace'] = $this->getTraceAsString();
        }

        yield $error;
    }
}
