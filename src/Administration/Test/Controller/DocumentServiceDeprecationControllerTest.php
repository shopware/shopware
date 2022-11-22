<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\DocumentServiceDeprecationController;
use Shopware\Core\Checkout\Document\Controller\DocumentController;
use Shopware\Core\Checkout\Document\DocumentGenerator\CreditNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DeliveryNoteGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\DocumentGeneratorRegistry;
use Shopware\Core\Checkout\Document\DocumentGenerator\InvoiceGenerator;
use Shopware\Core\Checkout\Document\DocumentGenerator\StornoGenerator;
use Shopware\Core\Checkout\Document\DocumentGeneratorController;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Event\DocumentOrderCriteriaEvent;
use Shopware\Core\Checkout\Document\FileGenerator\PdfGenerator;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @internal
 */
class DocumentServiceDeprecationControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    protected function setup(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);
    }

    /**
     * @dataProvider checkDataProvider
     */
    public function testCheck(\Closure $prepareContainer, bool $showWarning): void
    {
        $listener = function (DocumentOrderCriteriaEvent $event): void {
        };

        /**
         * @var ContainerInterface $container
         */
        $container = $prepareContainer($listener);

        $controller = new DocumentServiceDeprecationController(
            $container->get(DocumentService::class),
            $container->get(DocumentGeneratorRegistry::class),
            $container->get(PdfGenerator::class),
            $container->get(InvoiceGenerator::class),
            $container->get(DeliveryNoteGenerator::class),
            $container->get(StornoGenerator::class),
            $container->get(CreditNoteGenerator::class),
            $container->get(DocumentGeneratorController::class),
            $container->get(DocumentController::class),
            $container->get('event_dispatcher')
        );

        $result = $controller->check();

        static::assertInstanceOf(JsonResponse::class, $result);

        $content = \json_decode($result->getContent() ?: '', true);
        static::assertArrayHasKey('showWarning', $content);
        static::assertEquals($showWarning, $content['showWarning']);

        $container->get('event_dispatcher')->removeListener(DocumentOrderCriteriaEvent::class, $listener);
    }

    public function checkDataProvider(): iterable
    {
        return [
            'should not show warning' => [
                function (): ContainerInterface {
                    return $this->getContainer();
                },
                false,
            ],

            'should show warning when decorating deprecated service' => [
                function (): ContainerInterface {
                    $builder = new ContainerBuilder();

                    $builder->register(DocumentGeneratorRegistry::class, DocumentGeneratorRegistry::class)->addArgument([]);
                    $builder->register(DocumentGeneratorRegistry::class, DecoratingDocumentGeneratorRegistry::class)
                        ->setDecoratedService(DocumentGeneratorRegistry::class)
                        ->addArgument([]);

                    $builder->set(DocumentService::class, $this->getContainer()->get(DocumentService::class));
                    $builder->set(PdfGenerator::class, $this->getContainer()->get(PdfGenerator::class));
                    $builder->set(InvoiceGenerator::class, $this->getContainer()->get(InvoiceGenerator::class));
                    $builder->set(DeliveryNoteGenerator::class, $this->getContainer()->get(DeliveryNoteGenerator::class));
                    $builder->set(StornoGenerator::class, $this->getContainer()->get(StornoGenerator::class));
                    $builder->set(CreditNoteGenerator::class, $this->getContainer()->get(CreditNoteGenerator::class));
                    $builder->set(DocumentController::class, $this->getContainer()->get(DocumentController::class));
                    $builder->set(DocumentGeneratorController::class, $this->getContainer()->get(DocumentGeneratorController::class));
                    $builder->set('event_dispatcher', $this->getContainer()->get('event_dispatcher'));

                    return $builder->get('service_container');
                },
                true,
            ],

            'should show warning when listening deprecated event' => [
                function (\Closure $listener): ContainerInterface {
                    $this->getContainer()->get('event_dispatcher')->addListener(DocumentOrderCriteriaEvent::class, $listener);

                    return $this->getContainer();
                },
                true,
            ],
        ];
    }
}

/**
 * @internal
 */
class DecoratingDocumentGeneratorRegistry extends DocumentGeneratorRegistry
{
}
