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

export default [
    { name: 'shopService', provider: ShopApiService },
    { name: 'categoryService', provider: CategoryApiService },
    { name: 'productService', provider: ProductApiService },
    { name: 'productManufacturerService', provider: ProductManufacturerApiService },
    { name: 'orderService', provider: OrderApiService },
    { name: 'orderLineItemService', provider: OrderLineItemApiService },
    { name: 'orderDeliveryService', provider: OrderDeliveryApiService },
    { name: 'orderStateService', provider: OrderStateApiService },
    { name: 'customerService', provider: CustomerApiService },
    { name: 'customerGroupService', provider: CustomerGroupApiService },
    { name: 'paymentMethodService', provider: PaymentMethodApiService },
    { name: 'shippingMethodService', provider: ShippingMethodApiService },
    { name: 'countryService', provider: CountryApiService },
    { name: 'currencyService', provider: CurrencyApiService },
    { name: 'taxService', provider: TaxApiService },
    { name: 'mediaService', provider: MediaApiService }
];
