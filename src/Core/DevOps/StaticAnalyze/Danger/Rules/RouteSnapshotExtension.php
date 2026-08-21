<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * The `routes_without_schema` snapshot freezes the list of undocumented API routes; new
 * endpoints must ship an OpenAPI schema instead of extending the snapshot.
 *
 * @internal
 */
#[Package('framework')]
class RouteSnapshotExtension
{
    public function __invoke(Context $context): void
    {
        $routesSnapshot = $context->platform->pullRequest->getFiles()->get('tests/integration/Core/Framework/_snapshots/routes_without_schema/snapshot.json');
        if (!$routesSnapshot instanceof File) {
            return;
        }

        if ($routesSnapshot->additions !== 0) {
            $context->failure('Do not extend the `snapshot.json` file. Please create an open API schema for the new endpoint instead.');
        }
    }
}
