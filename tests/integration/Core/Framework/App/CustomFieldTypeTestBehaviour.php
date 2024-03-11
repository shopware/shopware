<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CustomFieldTypeTestBehaviour
{
    abstract protected static function getContainer(): ContainerInterface;

    protected function importCustomField(string $manifestPath): CustomFieldEntity
    {
        $manifest = Manifest::createFromXmlFile($manifestPath);

        $context = Context::createDefaultContext();
        $appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $appLifecycle->install($manifest, true, $context);

        /** @var EntityRepository<AppCollection> $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');
        $criteria = new Criteria();
        $criteria->addAssociation('customFieldSets.customFields');

        $apps = $appRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $apps);
        $app = $apps->first();
        static::assertNotNull($app);
        static::assertSame('SwagApp', $app->getName());

        $fieldSets = $app->getCustomFieldSets();
        static::assertNotNull($fieldSets);
        static::assertCount(1, $fieldSets);
        $customFieldSet = $fieldSets->first();
        static::assertNotNull($customFieldSet);
        static::assertSame('custom_field_test', $customFieldSet->getName());
        static::assertNotNull($customFieldSet->getCustomFields());

        static::assertCount(1, $customFieldSet->getCustomFields());

        $customField = $customFieldSet->getCustomFields()->first();
        static::assertNotNull($customField);

        return $customField;
    }
}
