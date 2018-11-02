<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\Account\AccountService;
use Shopware\Storefront\Page\Account\EmailSaveRequest;

class PageRequestTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    public function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testPageRequestExtension(): void
    {
        $checkoutContext = Generator::createContext();

        $extension = new MyCustomExtension('property value');

        $pageRequest = new EmailSaveRequest();
        $pageRequest->addExtension('customExtension', $extension);

        $originalData = [[
            'id' => $checkoutContext->getCustomer()->getId(),
            'customExtension' => $extension,
        ]];

        // array merge recrusive
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->expects(static::once())
            ->method('update')
            ->will(
                static::returnCallback(
                function ($data) use ($originalData) {
                    static::assertEquals($originalData, $data);

                    return $this->createMock(EntityWrittenContainerEvent::class);
                }
            ));

        $service = new AccountService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $customerRepository,
            $this->createMock(CheckoutContextPersister::class)
        );

        $service->saveEmail($pageRequest, $checkoutContext);
    }
}

class MyCustomExtension extends Struct
{
    /**
     * @var string
     */
    protected $myCustomProperty;

    public function __construct(string $myCustomExtension)
    {
        $this->myCustomProperty = $myCustomExtension;
    }

    public function getMyCustomProperty(): string
    {
        return $this->myCustomProperty;
    }

    public function setMyCustomProperty(string $myCustomProperty): void
    {
        $this->myCustomProperty = $myCustomProperty;
    }
}
