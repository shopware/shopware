<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Error;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class XmlValidationError extends Error
{
    /**
     * @var list<ErrorMessage>
     */
    protected array $errorMessages;

    /**
     * @param list<\LibXMLError> $errors
     */
    public function __construct(
        protected string $id,
        protected array $errors = []
    ) {
        $this->errorMessages = array_values(array_map(
            static function (\LibXMLError $error): ErrorMessage {
                $errorMessage = new ErrorMessage();
                $errorMessage->assign([
                    'message' => \sprintf('%s on line %d in column %d', trim($error->message), $error->line, $error->column),
                    'line' => $error->line,
                    'column' => $error->column,
                ]);

                return $errorMessage;
            },
            $errors
        ));

        $this->message = 'The export did not generate a valid XML file';

        parent::__construct($this->message);
    }

    public function getId(): string
    {
        return $this->getMessageKey() . $this->id;
    }

    public function getMessageKey(): string
    {
        return 'xml-validation-failed';
    }

    /**
     * @return array{errors: list<\LibXMLError>}
     */
    public function getParameters(): array
    {
        return ['errors' => $this->errors];
    }

    /**
     * @return list<ErrorMessage>
     */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }
}
