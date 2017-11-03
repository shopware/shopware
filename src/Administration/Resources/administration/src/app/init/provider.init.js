import EventEmitter from 'src/core/factory/event-emitter.factory';
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

export default function initializeProviders(app, configuration, done) {
    const httpClient = configuration.httpClient;
    const eventSystem = configuration.eventSystem;
    const stateContainer = configuration.stateContainer;
    const applicationState = configuration.applicationState;

    app.addProvider('httpClient', httpClient)
        .addProvider('eventSystem', eventSystem)
        .addProvider('eventEmitter', EventEmitter(eventSystem))
        .addProvider('stateContainer', stateContainer)
        .addProvider('productService', new ProductApiService(httpClient))
        .addProvider('orderService', new OrderApiService(httpClient))
        .addProvider('currencyService', new CurrencyApiService(httpClient))
        .addProvider('shopService', new ShopApiService(httpClient))
        .addProvider('orderStateService', new OrderStateApiService(httpClient))
        .addProvider('countryService', new CountryApiService(httpClient))
        .addProvider('orderLineItemService', new OrderLineItemApiService(httpClient))
        .addProvider('orderDeliveryService', new OrderDeliveryApiService(httpClient))
        .addProvider('shippingMethodService', new ShippingMethodApiService(httpClient))
        .addProvider('paymentMethodService', new PaymentMethodApiService(httpClient))
        .addProvider('customerService', new CustomerApiService(httpClient))
        .addProvider('customerGroupService', new CustomerGroupApiService(httpClient))
        .addProvider('productManufacturerService', new ProductManufacturerApiService(httpClient))
        .addProvider('taxService', new TaxApiService(httpClient))
        .addProvider('categoryService', new CategoryApiService(httpClient))
        .addProvider('mediaService', new MediaApiService(httpClient))
        .addProvider('loginService', LoginService(httpClient))
        .addProvider('applicationState', applicationState)
        .addProvider('menuService', MenuService);

    done(configuration);
}
