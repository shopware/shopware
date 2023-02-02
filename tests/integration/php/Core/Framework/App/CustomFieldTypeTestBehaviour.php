<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CustomFieldTypeTestBehaviour
{
    abstract protected function getContainer(): ContainerInterface;

    protected function importCustomField(string $manifestPath): CustomFieldEntity
    {
        $manifest = Manifest::createFromXmlFile($manifestPath);

        $context = Context::createDefaultContext();
        $appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $appLifecycle->install($manifest, true, $context);

        /** @var EntityRepository $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');
        $criteria = new Criteria();
        $criteria->addAssociation('customFieldSets.customFields');

        /** @var AppCollection $apps */
        $apps = $appRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $apps);
        /** @var AppEntity $app */
        $app = $apps->first();
        static::assertEquals('SwagApp', $app->getName());

        /** @var CustomFieldSetCollection $fieldSets */
        $fieldSets = $app->getCustomFieldSets();
        static::assertCount(1, $fieldSets);
        /** @var CustomFieldSetEntity $customFieldSet */
        $customFieldSet = $fieldSets->first();
        static::assertEquals('custom_field_test', $customFieldSet->getName());
        static::assertNotNull($customFieldSet->getCustomFields());

        static::assertCount(1, $customFieldSet->getCustomFields());

        /** @var CustomFieldCollection $customFields */
        $customFields = $customFieldSet->getCustomFields();
        /** @var CustomFieldEntity $customField */
        $customField = $customFields->first();

        return $customField;
    }
}
