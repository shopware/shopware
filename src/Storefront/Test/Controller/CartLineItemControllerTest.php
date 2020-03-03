<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class CartLineItemControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testErrorBehaviourInFlashMessages(): void
    {
        $productId = Uuid::randomHex();

        $data = $this->getLineItemAddPayload($productId);

        $response = $this->request(
            'POST',
            '/checkout/line-item/add',
            $this->tokenize('frontend.checkout.line-item.add', $data)
        );

        static::assertSame(
            ['warning' => ['checkout.product-not-found']],
            $this->getContainer()->get('session')->getFlashBag()->all()
        );
        static::assertTrue($response->isRedirect(), $response->getContent());
    }

    private function getLineItemAddPayload(string $productId): array
    {
        return [
            'redirectTo' => 'frontend.cart.offcanvas',
            'lineItems' => [
                $productId => [
                    'id' => $productId,
                    'referencedId' => $productId,
                    'type' => 'product',
                    'stackable' => 1,
                    'removable' => 1,
                    'quantity' => 1,
                ],
            ],
        ];
    }
}
