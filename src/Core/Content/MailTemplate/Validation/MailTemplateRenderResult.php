<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Validation;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
class MailTemplateRenderResult extends Struct
{
    public const TYPE_SUCCESS = 'success';

    public const TYPE_ERROR = 'error';

    private function __construct(
        private readonly string $type,
        private readonly string $content,
        private readonly ?string $errorTitle = null,
        private readonly ?string $errorMessage = null,
    ) {
    }

    public static function success(string $content): self
    {
        return new self(self::TYPE_SUCCESS, $content);
    }

    public static function errorFromThrowable(\Throwable $error): self
    {
        return new self(
            self::TYPE_ERROR,
            $error->getMessage(),
            self::getErrorTitleFromThrowable($error),
            self::getErrorMessageFromThrowable($error),
        );
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getErrorTitle(): ?string
    {
        return $this->errorTitle;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'type' => $this->getType(),
            'content' => $this->getContent(),
        ];

        if ($this->getErrorTitle() !== null) {
            $data['errorTitle'] = $this->getErrorTitle();
        }

        if ($this->getErrorMessage() !== null) {
            $data['errorMessage'] = $this->getErrorMessage();
        }

        return $data;
    }

    private static function getErrorTitleFromThrowable(\Throwable $error): string
    {
        if ($error instanceof HttpException && $error->is(AdapterException::INVALID_TEMPLATE_SYNTAX)) {
            return 'Twig syntax error';
        }

        if ($error instanceof HttpException && $error->is(AdapterException::STRING_TEMPLATE_RENDERING_FAILED)) {
            return 'Rendering error';
        }

        return 'Error';
    }

    private static function getErrorMessageFromThrowable(\Throwable $error): string
    {
        if ($error instanceof HttpException && $error->getParameter('message') !== null) {
            return (string) $error->getParameter('message');
        }

        return $error->getMessage();
    }
}
