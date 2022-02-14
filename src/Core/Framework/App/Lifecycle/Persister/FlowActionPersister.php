<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\Framework\App\FlowAction\Xml\Action;
use Shopware\Core\Framework\App\FlowAction\Xml\Metadata;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Event\UserAware;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class FlowActionPersister
{
    private Connection $connection;

    private EntityRepositoryInterface $flowActionsRepository;

    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $flowActionsRepository
    ) {
        $this->connection = $connection;
        $this->flowActionsRepository = $flowActionsRepository;
    }

    public function updateActions(FlowAction $flowAction, string $appId, Context $context): void
    {
        $flowActions = $flowAction->getActions();
        if ($flowActions === null) {
            return;
        }

        $data = [];
        foreach ($flowActions->getActions() as $action) {
            $payload = $this->toArrayAction($action, $appId, $flowAction);
            $data[] = $payload;
        }

        if (!empty($data)) {
            $this->flowActionsRepository->create($data, $context);
        }
    }

    private function toArrayAction(Action $action, string $appId, FlowAction $flowActions): array
    {
        return [
            'appId' => $appId,
            'name' => 'app.' . $action->getMeta()->getName(),
            'iconRaw' => $this->getIcon($action->getMeta()->getIcon(), $flowActions),
            'swIcon' => $action->getMeta()->getSwIcon(),
            'url' => $action->getMeta()->getUrl(),
            'translations' => $this->toArrayTranslations($action->getMeta()),
            'parameters' => array_map(function ($parameter) {
                return $parameter->jsonSerialize();
            }, $action->getParameters()->getParameters()),
            'config' => array_map(function ($config) {
                return $config->jsonSerialize();
            }, $action->getConfig()->getConfig()),
            'headers' => array_map(function ($header) {
                return $header->jsonSerialize();
            }, $action->getHeaders()->getParameters()),
            'requirements' => array_map(function ($aware) {
                return $this->awareMappings($aware);
            }, $action->getMeta()->getRequirements()),
        ];
    }

    private function fetchLanguageIdByName(array $isoCodes): array
    {
        return $this->connection->fetchAll(
            'SELECT `language`.id, `locale`.code FROM `language`
            INNER JOIN locale ON language.translation_code_id = locale.id
            WHERE `code` IN (:codes)',
            ['codes' => $isoCodes],
            ['codes' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function toArrayTranslations(Metadata $meta): array
    {
        $labels = $meta->getLabel();
        $descriptions = $meta->getDescription();

        $isoCodes = array_keys($labels);
        $languages = $this->fetchLanguageIdByName($isoCodes);

        $translations = [];
        foreach ($languages as $language) {
            $translations[] = [
                'label' => $labels[$language['code']],
                'description' => $descriptions[$language['code']] ?? null,
                'languageId' => Uuid::fromBytesToHex($language['id']),
            ];
        }

        return $translations;
    }

    private function awareMappings(string $aware): ?string
    {
        return [
            'order' => OrderAware::class,
            'customer' => CustomerAware::class,
            'sales_channel' => SalesChannelAware::class,
            'mail' => MailAware::class,
            'user' => UserAware::class,
        ][$aware] ?? null;
    }

    private function getIcon(?string $icon, FlowAction $flowAction): ?string
    {
        if (!$icon) {
            return null;
        }

        $iconPath = sprintf('%s/%s', $flowAction->getPath(), $icon);
        $icon = @file_get_contents($iconPath);

        if (!$icon) {
            return null;
        }

        return $icon;
    }
}
