<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Controller\Exception\ExpectedUserHttpException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Package('system-settings')]
class UserConfigController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $userConfigRepository,
        private readonly Connection $connection
    ) {
    }

    #[Route(path: '/api/_info/config-me', name: 'api.config_me.get', defaults: ['auth_required' => true, '_routeScope' => ['administration']], methods: ['GET'])]
    public function getConfigMe(Context $context, Request $request): Response
    {
        $userConfigs = $this->getOwnUserConfig($context, $request->query->all('keys'));
        $data = [];
        /** @var UserConfigEntity $userConfig */
        foreach ($userConfigs as $userConfig) {
            $data[$userConfig->getKey()] = $userConfig->getValue();
        }

        return new JsonResponse(['data' => $data]);
    }

    #[Route(path: '/api/_info/config-me', name: 'api.config_me.update', defaults: ['auth_required' => true, '_routeScope' => ['administration']], methods: ['POST'])]
    public function updateConfigMe(Context $context, Request $request): Response
    {
        $postUpdateConfigs = $request->request->all();

        if (empty($postUpdateConfigs)) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $this->massUpsert($context, $postUpdateConfigs);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function getOwnUserConfig(Context $context, array $keys): array
    {
        $userId = $this->getUserId($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        if (!empty($keys)) {
            $criteria->addFilter(new EqualsAnyFilter('key', $keys));
        }

        return $this->userConfigRepository->search($criteria, $context)->getElements();
    }

    private function getUserId(Context $context): string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, $context->getSource()::class);
        }

        $userId = $context->getSource()->getUserId();
        if (!$userId) {
            throw new ExpectedUserHttpException();
        }

        return $userId;
    }

    private function massUpsert(Context $context, array $postUpdateConfigs): void
    {
        $userId = $this->getUserId($context);
        $userConfigs = $this->getOwnUserConfig($context, array_keys($postUpdateConfigs));
        $userConfigsGroupByKey = [];

        /** @var UserConfigEntity $userConfig */
        foreach ($userConfigs as $userConfig) {
            $userConfigsGroupByKey[$userConfig->getKey()] = $userConfig->getId();
        }

        $queue = new MultiInsertQueryQueue($this->connection, 250, false, true);
        foreach ($postUpdateConfigs as $key => $value) {
            $data = [
                'value' => Json::encode($value),
                'user_id' => Uuid::fromHexToBytes($userId),
                'key' => $key,
                'id' => Uuid::randomBytes(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
            if (\array_key_exists($key, $userConfigsGroupByKey)) {
                $data['id'] = Uuid::fromHexToBytes($userConfigsGroupByKey[$key]);
            }

            $queue->addInsert(UserConfigDefinition::ENTITY_NAME, $data);
        }

        $queue->execute();
    }
}
