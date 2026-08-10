<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentFeatureDefinition;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class DocumentLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function install(AppPersistContext $context): void
    {
        $this->assertNoCollision($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->assertNoCollision($context);
    }

    private function assertNoCollision(AppPersistContext $context): void
    {
        $documents = $context->manifest->getDocuments();

        if ($documents === null) {
            return;
        }

        $appName = $context->app->getName();

        /** @var array<string, string> $claimedBy */
        $claimedBy = $this->connection->fetchAllKeyValue(
            'SELECT `name`, `app_name` FROM `app_feature` WHERE `type` = :type AND `app_name` != :appName',
            ['type' => DocumentFeatureDefinition::TYPE, 'appName' => $appName],
        );

        foreach ($documents->getDocumentTypes() as $documentType) {
            $identifier = $documentType['identifier'];

            if (DocumentType::tryFrom($identifier) !== null) {
                throw AppException::documentTypeShadowsCoreType($identifier);
            }

            if (isset($claimedBy[$identifier])) {
                throw AppException::documentTypeAlreadyRegistered($identifier, $claimedBy[$identifier]);
            }
        }
    }
}
