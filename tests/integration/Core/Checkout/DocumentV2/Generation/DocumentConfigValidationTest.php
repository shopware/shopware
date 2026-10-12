<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\DocumentV2\Generation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentConfigValidationTest extends TestCase
{
    use DocumentV2Trait;

    public function testInvalidConfigurationLeavesNumberAndOrderVersionsUnchanged(): void
    {
        $this->context = Context::createDefaultContext();
        $addressId = Uuid::randomHex();
        $customerId = $this->createCustomer(
            ['defaultShippingAddressId' => $addressId],
            $this->buildDemoShippingAddress($addressId),
        );
        $ids = new IdsCollection();
        $ids->set('customer', $customerId);
        $order = (new OrderBuilder($ids, 'order'))
            ->add('languageId', Defaults::LANGUAGE_SYSTEM)
            ->addAddress('billing_address', [
                'id' => $ids->get('billing_address'),
                'country' => ['id' => $this->getValidCountryId()],
                'salutationId' => $this->getValidSalutationId(),
            ])
            ->orderCustomer('Customer', 'customer')
            ->addTransaction('transaction')
            ->build();
        static::getContainer()->get('order.repository')->create([$order], $this->context);
        $this->seedDemoBaseConfig('invoice');
        static::getContainer()->get(SystemConfigService::class)->set(
            'core.basicInformation.companyCountryId',
            'invalid',
            TestDefaults::SALES_CHANNEL,
        );
        static::getContainer()->get(DocumentConfigLoader::class)->reset();

        $connection = static::getContainer()->get(Connection::class);
        $numberGenerator = static::getContainer()->get(AbstractNumberRangeValueGenerator::class);
        $numberBefore = $numberGenerator->getValue('document_invoice', $this->context, TestDefaults::SALES_CHANNEL, preview: true);
        $versionsBefore = $connection->fetchOne('SELECT COUNT(*) FROM `version`');

        $this->expectExceptionObject(DocumentV2Exception::configMissingRequiredFields(
            DocumentCompanyInfo::class,
            'invoice',
            'companyCountry',
        ));

        try {
            static::getContainer()->get(DocumentGenerator::class)->generate(
                new DocumentGenerationRequest($ids->get('order'), 'invoice', ['html']),
                $this->context,
            );
        } finally {
            static::assertSame($numberBefore, $numberGenerator->getValue('document_invoice', $this->context, TestDefaults::SALES_CHANNEL, preview: true));
            static::assertSame($versionsBefore, $connection->fetchOne('SELECT COUNT(*) FROM `version`'));
            static::assertSame(1, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM `order` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('order'))],
            ));
        }
    }
}
