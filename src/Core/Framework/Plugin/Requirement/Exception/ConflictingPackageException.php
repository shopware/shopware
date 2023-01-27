<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement\Exception;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ConflictingPackageException extends RequirementException
{
    public function __construct(
        string $conflictSource,
        string $conflictTarget,
        string $actualVersion
    ) {
        parent::__construct(
            '"{{ conflictSource }}" conflicts with plugin/package "{{ conflictTarget }} {{ version }}"',
            [
                'conflictSource' => $conflictSource,
                'conflictTarget' => $conflictTarget,
                'version' => $actualVersion,
            ]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_CONFLICTED';
    }
}
