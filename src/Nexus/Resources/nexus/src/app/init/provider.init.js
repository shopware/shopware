import EventEmitter from 'src/core/factory/event-emitter.factory';
import ProductService from 'src/core/service/api/product/product.service';
import OrderService from 'src/core/service/api/order/order.service';
import CustomerService from 'src/core/service/api/customer/customer.service';
import CustomerGroupService from 'src/core/service/api/customer_group/customer_group.service';
import PaymentMethodService from 'src/core/service/api/payment_method/payment_method.service';
import OrderLineItemService from 'src/core/service/api/order_line_item/order_line_item.service';
import ShippingMethodService from 'src/core/service/api/shipping_method/shipping_method.service';
import CountryService from 'src/core/service/api/country/country.service';
import OrderDeliveryService from 'src/core/service/api/order_delivery/order_delivery.service';
import CurrencyService from 'src/core/service/api/currency/currency.service';
import ShopService from 'src/core/service/api/shop/shop.service';
import OrderStateService from 'src/core/service/api/order_state/order_state.service';
import ProductManufacturerService from 'src/core/service/api/product_manufacturer/product_manufacturer.service';
import TaxService from 'src/core/service/api/tax/tax.service';
import CategoryService from 'src/core/service/api/category/category.service';
import MediaService from 'src/core/service/api/media/media.service';
import LoginService from 'src/core/service/api/login/login.service';
import MenuService from 'src/app/service/menu.service';

export default function initializeProviders(app, configuration, done) {
    const httpClient = configuration.httpClient;
    const eventSystem = configuration.eventSystem;
    const stateContainer = configuration.stateContainer;
    const applicationState = configuration.applicationState;

    app.addProvider('httpClient', httpClient)
        .addProvider('eventSystem', eventSystem)
        .addProvider('eventEmitter', EventEmitter(eventSystem))
        .addProvider('stateContainer', stateContainer)
        .addProvider('productService', ProductService(httpClient))
        .addProvider('orderService', OrderService(httpClient))
        .addProvider('currencyService', CurrencyService(httpClient))
        .addProvider('shopService', ShopService(httpClient))
        .addProvider('orderStateService', OrderStateService(httpClient))
        .addProvider('countryService', CountryService(httpClient))
        .addProvider('orderLineItemService', OrderLineItemService(httpClient))
        .addProvider('orderDeliveryService', OrderDeliveryService(httpClient))
        .addProvider('shippingMethodService', ShippingMethodService(httpClient))
        .addProvider('paymentMethodService', PaymentMethodService(httpClient))
        .addProvider('customerService', CustomerService(httpClient))
        .addProvider('customerGroupService', CustomerGroupService(httpClient))
        .addProvider('productManufacturerService', ProductManufacturerService(httpClient))
        .addProvider('taxService', TaxService(httpClient))
        .addProvider('categoryService', CategoryService(httpClient))
        .addProvider('mediaService', MediaService(httpClient))
        .addProvider('loginService', LoginService(httpClient))
        .addProvider('applicationState', applicationState)
        .addProvider('menuService', MenuService);

    done(configuration);
}
