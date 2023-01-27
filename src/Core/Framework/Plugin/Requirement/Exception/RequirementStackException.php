<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class RequirementStackException extends ShopwareHttpException
{
    /**
     * @var RequirementException[]
     */
    private readonly array $requirements;

    public function __construct(
        string $method,
        RequirementException ...$requirements
    ) {
        $this->requirements = $requirements;

        parent::__construct(
            'Could not {{ method }} plugin, got {{ failureCount }} failure(s). {{ errors }}',
            [
                'method' => $method,
                'failureCount' => \count($requirements),
                'errors' => $this->getInnerExceptionsDetails(),
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_REQUIREMENTS_FAILED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FAILED_DEPENDENCY;
    }

    /**
     * @return RequirementException[]
     */
    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function getErrors(bool $withTrace = false): \Generator
    {
        foreach ($this->requirements as $exception) {
            yield from $exception->getErrors($withTrace);
        }
    }

    private function getInnerExceptionsDetails(): string
    {
        $details = [];
        foreach ($this->requirements as $innerException) {
            $details[] = "\n" . $innerException->getMessage();
        }

        return implode('', $details);
    }
}
