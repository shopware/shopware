<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CustomFieldTypeTestBehaviour
{
    use StorefrontPluginRegistryTestBehaviour;

    abstract protected function getContainer(): ContainerInterface;

    protected function importCustomField(string $manifestPath): CustomFieldEntity
    {
        $manifest = Manifest::createFromXmlFile($manifestPath);

        $context = Context::createDefaultContext();
        $appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $appLifecycle->install($manifest, true, $context);

        /** @var EntityRepositoryInterface $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');
        $criteria = new Criteria();
        $criteria->addAssociation('customFieldSets.customFields');

        /** @var AppCollection $apps */
        $apps = $appRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $apps);
        static::assertEquals('SwagApp', $apps->first()->getName());

        static::assertCount(1, $apps->first()->getCustomFieldSets());
        $customFieldSet = $apps->first()->getCustomFieldSets()->first();
        static::assertEquals('custom_field_test', $customFieldSet->getName());

        static::assertCount(1, $customFieldSet->getCustomFields());

        return $customFieldSet->getCustomFields()->first();
    }
}
