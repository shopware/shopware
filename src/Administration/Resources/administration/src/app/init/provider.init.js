/* eslint-disable */
import LoginService from 'src/core/service/login.service';
import MenuService from 'src/app/service/menu.service';
import ShopApiService from 'src/core/service/api/shop.api.service';
import CategoryApiService from 'src/core/service/api/category.api.service';
import ProductApiService from 'src/core/service/api/product.api.service';
import ProductManufacturerApiService from 'src/core/service/api/product-manufacturer.api.service';
import OrderApiService from 'src/core/service/api/order.api.service';
import OrderLineItemApiService from 'src/core/service/api/order-line-item.api.service';
import OrderDeliveryApiService from 'src/core/service/api/order-delivery.api.service';
import OrderStateApiService from 'src/core/service/api/order-state.api.service';
import CustomerApiService from 'src/core/service/api/customer.api.service';
import CustomerGroupApiService from 'src/core/service/api/customer-group.api.service';
import PaymentMethodApiService from 'src/core/service/api/payment-method.api.service';
import ShippingMethodApiService from 'src/core/service/api/shipping-method.api.service';
import CountryApiService from 'src/core/service/api/country.api.service';
import CurrencyApiService from 'src/core/service/api/currency.api.service';
import TaxApiService from 'src/core/service/api/tax.api.service';
import MediaApiService from 'src/core/service/api/media.api.service';

// Shopware.Container.httpClient;
// Shopware.Component.register();

export default function initializeProviders() {
    console.log(this);

    this.addServiceProvider('productService', (container) => {
        return new ProductApiService(container.httpClient)
    });

    this.addServiceProvider('orderService', (container) => {
        return new OrderApiService(container.httpClient);
    });

    this.addServiceProvider('menuService', () => {
        return MenuService();
    });

    return {};
    // Register the providers
    /* return diContainer.service('provider.eventEmitter', EventEmitter, 'initializer.eventSystem')
        .service('provider.productService', ProductApiService, 'initializer.httpClient')
        .service('provider.orderService', OrderApiService, 'initializer.httpClient')
        .service('provider.currencyService', CurrencyApiService, 'initializer.httpClient')
        .service('provider.shopService', ShopApiService, 'initializer.httpClient')
        .service('provider.orderStateService', OrderStateApiService, 'initializer.httpClient')
        .service('provider.countryService', CountryApiService, 'initializer.httpClient')
        .service('provider.orderDeliveryService', OrderDeliveryApiService, 'initializer.httpClient')
        .service('provider.orderLineItemService', OrderLineItemApiService, 'initializer.httpClient')
        .service('provider.shippingMethodService', ShippingMethodApiService, 'initializer.httpClient')
        .service('provider.paymentMethodService', PaymentMethodApiService, 'initializer.httpClient')
        .service('provider.customerService', CustomerApiService, 'initializer.httpClient')
        .service('provider.customerGroupService', CustomerGroupApiService, 'initializer.httpClient')
        .service('provider.productManufacturerService', ProductManufacturerApiService, 'initializer.httpClient')
        .service('provider.taxService', TaxApiService, 'initializer.httpClient')
        .service('provider.categoryService', CategoryApiService, 'initializer.httpClient')
        .service('provider.mediaService', MediaApiService, 'initializer.httpClient')
        .service('provider.loginService', LoginService, 'initializer.httpClient')
        .service('provider.menuService', MenuService);
        */
}
