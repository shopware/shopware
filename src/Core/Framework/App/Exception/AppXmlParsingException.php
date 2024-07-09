<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class AppXmlParsingException extends AppException
{
    /**
     * @deprecated tag:v6.7.0 - constructor will be removed, use static methods instead
     */
    public function __construct(
        string $xmlFile,
        string $message
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.7.0.0')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::XML_PARSE_ERROR,
            'Unable to parse file "{{ file }}". Message: {{ message }}',
            ['file' => $xmlFile, 'message' => $message]
        );
    }

    public static function cannotParseFile(string $xmlFile, string $message): self
    {
        // Call plain parent constructor in 6.7.0
        /** @var self $self */
        $self = Feature::silent(
            'v6.7.0.0',
            static fn () => new self('', '')
        );
        $self->statusCode = Response::HTTP_BAD_REQUEST;
        $self->errorCode = self::XML_PARSE_ERROR;
        $self->parameters = ['file' => $xmlFile, 'message' => $message];
        $self->message = $self->parse('Unable to parse file "{{ file }}". Message: {{ message }}', $self->parameters);

        return $self;
    }

    public static function cannotParseContent(string $message): self
    {
        // Call plain parent constructor in 6.7.0
        /** @var self $self */
        $self = Feature::silent(
            'v6.7.0.0',
            static fn () => new self('', '')
        );
        $self->statusCode = Response::HTTP_BAD_REQUEST;
        $self->errorCode = self::XML_PARSE_ERROR;
        $self->parameters = ['message' => $message];
        $self->message = $self->parse('Unable to parse XML content. Message: {{ message }}', $self->parameters);

        return $self;
    }
}
