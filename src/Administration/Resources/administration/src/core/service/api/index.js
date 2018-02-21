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

export default {
    shopService: ShopApiService,
    categoryService: CategoryApiService,
    productService: ProductApiService,
    productManufacturerService: ProductManufacturerApiService,
    orderService: OrderApiService,
    orderLineItemService: OrderLineItemApiService,
    orderDeliveryService: OrderDeliveryApiService,
    orderStateService: OrderStateApiService,
    customerService: CustomerApiService,
    customerGroupService: CustomerGroupApiService,
    paymentMethodService: PaymentMethodApiService,
    shippingMethodService: ShippingMethodApiService,
    countryService: CountryApiService,
    currencyService: CurrencyApiService,
    taxService: TaxApiService,
    mediaService: MediaApiService
};
