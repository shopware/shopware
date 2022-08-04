import random
import uuid


class StoreApi:
    context: None

    def __init__(self, client, context):
        self.context = context
        self.client = client
        self.currency_id = random.choice(self.context.sales_channel['currencies'])
        self.language_id = random.choice(self.context.sales_channel['languages'])
        self.token = str(uuid.uuid4()).replace('-', '')
        self.switch_context({
            'currencyId': self.currency_id,
            'languageId': self.language_id
        })

    def home(self):
        return self.request('/store-api/category/home', name='home')

    def navigation(self, activeId = 'main-navigation'):
        return self.request('/store-api/navigation/' + activeId + '/main-navigation', name = 'main-navigation')

    def footer(self, activeId = 'footer-navigation'):
        return self.request('/store-api/navigation/' + activeId + '/footer-navigation', name = 'footer-navigation')

    def service(self, activeId = 'service-navigation'):
        return self.request('/store-api/navigation/' + activeId + '/service-navigation', name = 'service-navigation')

    def shipping_methods(self):
        return self.request('/store-api/shipping-method', name='shipping-methods')

    def payment_methods(self):
        return self.request('/store-api/payment-method', name='payment-methods')

    def languages(self):
        return self.request('/store-api/language', name='languages')

    def currencies(self):
        return self.request('/store-api/currency', name='currencies')

    def salutations(self):
        return self.request('/store-api/salutation', name='salutations')

    def countries(self):
        return self.request('/store-api/country', name='countries')

    def search(self):
        return self.request('/store-api/search', name='search', parameters = {'search': random.choice(self.context.keywords)})

    def suggest(self):
        return self.request('/store-api/search-suggest', name='suggest', parameters = {'search': random.choice(self.context.keywords)})

    def cart(self):
        return self.request('/store-api/checkout/cart', name='cart')

    def product(self):
        return self.request('/store-api/product/' + random.choice(self.context.product_ids), name='product')

    def listing(self):
        return self.request('/store-api/category/' + random.choice(self.context.category_ids), name='listing')

    def add_product_to_cart(self):
        id = random.choice(self.context.product_ids)

        return self.request(
            '/store-api/checkout/cart/line-item',
            name='add-product-to-cart',
            parameters = {
                'items': [{'type': 'product', 'id': id, 'referencedId': id}]
            }
        )

    def order(self):
        return self.request('/store-api/checkout/order', name='order')

    def register(self):
        self.email = 'user-' + str(uuid.uuid4()).replace('-', '') + '@example.com'

        response = self.request('/store-api/account/register', name='register', parameters={
            'storefrontUrl': self.context.sales_channel['domain'],
            'salutationId': self.context.sales_channel['salutationId'],
            'firstName': 'Firstname',
            'lastName': 'Lastname',
            'email': self.email,
            'password': 'shopware',
            'acceptedDataProtection': True,
            'billingAddress': {
                'salutationId': self.context.sales_channel['salutationId'],
                'street': 'Test street',
                'zipcode': '11111',
                'city': 'Test city',
                'countryId': self.context.sales_channel['countryId']
            }
        })

        self.token = response.headers['sw-context-token']
        return response

    def switch_context(self, parameters):
        response = self.request('/store-api/context', name='context-switch', parameters=parameters, method='PATCH')

        self.token = response.headers['sw-context-token']

        return response

    def get_headers(self):
        return  {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'sw-context-token': self.token,
            'sw-access-key': self.context.sales_channel['access_key']
        }

    def request(self, url, name, parameters = {}, method = 'POST'):
        headers = self.get_headers()

        if method == 'POST':
            response = self.client.post(self.context.url + url, json=parameters, headers=headers, name=name)
        elif method == 'PATCH':
            response = self.client.patch(self.context.url + url, json=parameters, headers=headers, name=name)
        else:
            response = self.client.get(self.context.url + url, headers=headers, name=name)

        if response.status_code in [200, 204]:
            return response

        return response
