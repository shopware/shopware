<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Write\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class WriteOutcomeTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testAPartialCustomFieldsUpdateResultsInAllCustomFields(): void
    {
        $ids = new IdsCollection();
        $productRepository = static::getContainer()->get('product.repository');
        $productRepository->create([
            (new ProductBuilder($ids, 'p1'))->price(10)->customField('colour', 'red')->build(),
        ], Context::createDefaultContext());

        $writeOutcome = new WriteOutcome(new RowLoader(static::getContainer()->get(Connection::class)));
        $customFields = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            PreWriteValidationEvent::class,
            static function (PreWriteValidationEvent $event) use ($writeOutcome, &$customFields): void {
                foreach ($writeOutcome->rows($event->getCommandsForEntity('product_translation'), ['custom_fields']) as $row) {
                    $customFields = $row->getResultingValue('custom_fields');
                }
            }
        );

        $productRepository->update([['id' => $ids->get('p1'), 'customFields' => ['size' => 'L']]], Context::createDefaultContext());

        static::assertIsString($customFields);
        static::assertSame(['colour' => 'red', 'size' => 'L'], Json::decodeToArray($customFields));
    }
}
