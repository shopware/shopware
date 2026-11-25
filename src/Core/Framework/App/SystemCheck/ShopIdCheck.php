<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\SystemCheck;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Whether the stored shop ID still matches this shop. When its fingerprints (APP_URL, installation path,
 * sales channel domains) changed, the shop stops talking to every app registered at an app server until the
 * change is resolved.
 *
 * @internal
 */
#[Package('framework')]
class ShopIdCheck extends BaseCheck
{
    public function __construct(
        private readonly ShopIdProvider $shopIdProvider,
        private readonly Connection $connection,
    ) {
    }

    public function run(): Result
    {
        try {
            $comparison = $this->shopIdProvider->compareFingerprints();
        } catch (AppException $e) {
            if (!$e->is(AppException::APP_URL_NOT_CONFIGURED, AppException::APP_URL_INVALID)) {
                throw $e;
            }

            return new Result($this->name(), Status::ERROR, 'APP_URL is missing or invalid', false, ['error' => $e->getMessage()]);
        }

        if ($comparison === null || $comparison->isMatching()) {
            return new Result($this->name(), Status::OK, 'Shop identity unchanged', true);
        }

        $fingerprints = [];
        foreach ($comparison->mismatchingFingerprints as $mismatch) {
            $fingerprints[] = ['identifier' => $mismatch->identifier, 'storedValue' => $mismatch->storedStamp, 'currentValue' => $mismatch->expectedStamp, 'matches' => false];
        }
        foreach ($comparison->matchingFingerprints as $match) {
            $fingerprints[] = ['identifier' => $match->identifier, 'storedValue' => $match->storedStamp, 'currentValue' => $match->storedStamp, 'matches' => true];
        }

        $appsOnHold = $this->appsRegisteredAtAppServers();

        // without registered apps the shop ID is updated on its next use, nothing is on hold
        if ($appsOnHold === []) {
            return new Result($this->name(), Status::WARNING, 'Shop identity changed', true, ['fingerprints' => $fingerprints]);
        }

        return new Result($this->name(), Status::ERROR, 'Shop identity changed', false, ['fingerprints' => $fingerprints, 'appsOnHold' => $appsOnHold]);
    }

    public function category(): Category
    {
        return Category::EXTERNAL;
    }

    public function name(): string
    {
        return 'ShopId';
    }

    public function tags(): array
    {
        return ['apps'];
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return [
            SystemCheckExecutionContext::WEB,
            SystemCheckExecutionContext::CLI,
            SystemCheckExecutionContext::RECURRENT,
        ];
    }

    /**
     * @return list<string> labels of the apps registered at their app server
     */
    private function appsRegisteredAtAppServers(): array
    {
        /** @var list<string> $labels */
        $labels = $this->connection->fetchFirstColumn(
            'SELECT COALESCE(app_translation.label, app.name)
             FROM app
             LEFT JOIN app_translation ON app_translation.app_id = app.id AND app_translation.language_id = :languageId
             WHERE app.app_secret IS NOT NULL
             ORDER BY 1',
            ['languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
        );

        return $labels;
    }
}
