<?php declare(strict_types=1);

namespace Shopware\Storefront\Test;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Page\Account\AccountService;
use Shopware\Storefront\Page\Account\EmailSaveRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PageRequestTest extends KernelTestCase
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    private $connection;

    public function setUp()
    {
        self::bootKernel();

        $this->connection = self::$container->get(Connection::class);
    }

    public function testPageRequestExtension()
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
