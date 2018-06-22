import ShopApiService from 'src/core/service/api/shop.api.service';
import CatalogApiService from 'src/core/service/api/catalog.api.service';
import CategoryApiService from 'src/core/service/api/category.api.service';
import ProductApiService from 'src/core/service/api/product.api.service';
import ProductManufacturerApiService from 'src/core/service/api/product-manufacturer.api.service';
import OrderApiService from 'src/core/service/api/order.api.service';
import OrderLineItemApiService from 'src/core/service/api/order-line-item.api.service';
import OrderDeliveryApiService from 'src/core/service/api/order-delivery.api.service';
import OrderStateApiService from 'src/core/service/api/order-state.api.service';
import CustomerApiService from 'src/core/service/api/customer.api.service';
import CustomerAddressApiService from 'src/core/service/api/customer-address.api.service';
import CustomerGroupApiService from 'src/core/service/api/customer-group.api.service';
import PaymentMethodApiService from 'src/core/service/api/payment-method.api.service';
import ShippingMethodApiService from 'src/core/service/api/shipping-method.api.service';
import CountryApiService from 'src/core/service/api/country.api.service';
import CurrencyApiService from 'src/core/service/api/currency.api.service';
import TaxApiService from 'src/core/service/api/tax.api.service';
import RuleApiService from 'src/core/service/api/rule.api.service';
import MediaApiService from 'src/core/service/api/media.api.service';
import TouchpointApiService from 'src/core/service/api/touchpoint.api.service';

export default {
    shopService: ShopApiService,
    catalogService: CatalogApiService,
    categoryService: CategoryApiService,
    productService: ProductApiService,
    productManufacturerService: ProductManufacturerApiService,
    orderService: OrderApiService,
    orderLineItemService: OrderLineItemApiService,
    orderDeliveryService: OrderDeliveryApiService,
    orderStateService: OrderStateApiService,
    customerService: CustomerApiService,
    customerAddressService: CustomerAddressApiService,
    customerGroupService: CustomerGroupApiService,
    paymentMethodService: PaymentMethodApiService,
    shippingMethodService: ShippingMethodApiService,
    countryService: CountryApiService,
    currencyService: CurrencyApiService,
    taxService: TaxApiService,
    ruleService: RuleApiService,
    mediaService: MediaApiService,
    touchpointService: TouchpointApiService
};
