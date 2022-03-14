import random
import json
import uuid
import time
from bs4 import BeautifulSoup

class Storefront:
    def __init__(self, client, context, url = '', previous = None, params = {}, name = 'home-page'):
        self.client = client
        self.context = context
        self.previous = previous
        self.params = params
        self.url = url
        self.name = name
        self.__do_request()

    def go_back(self):
        self.previous.__do_request()
        return self.previous

    def refresh(self):
        self.__do_request()
        return self

    def go_to_home(self):
        return Storefront(self.client, self.context, '', self)

    def go_to_listing(self):
        return Storefront(self.client, self.context, random.choice(self.context.listings), self, {}, self.__build_name('listing-page'))

    def go_to_product(self):
        return Storefront(self.client, self.context, random.choice(self.context.product_urls), self, {}, self.__build_name('product-page'))

    def go_to_next_page(self):
        params = self.params
        params['p'] = params['p'] + 1 if 'p' in params else 2

        return Storefront(self.client, self.context, self.url, self, params, self.name)

    def do_search(self):
        return Storefront(self.client, self.context, '', self, {'search': random.choice(self.context.keywords)}, self.__build_name('search-page'))

    def add_product_to_cart(self):
        self.__add_product_to_cart()

        return self.go_to_cart()

    def go_to_cart(self):
        return Storefront(self.client, self.context, '/checkout/cart', self, {}, self.__build_name('cart-page'))

    def go_to_confirm(self):
        return Storefront(self.client, self.context, '/checkout/confirm', self, {}, self.__build_name('confirm-page'))

    def make_order(self):
        self.client.post('/checkout/order', name=self.__build_name('order'), data={'tos': 'on'})
        return self.go_to_home()

    def logout(self):
        self.client.get('/account/logout', name=self.__build_name('logout'))
        return self.go_to_home()

    def go_to_account(self):
        return Storefront(self.client, self.context, '/account', self, {}, self.__build_name('account-page'))

    def go_to_account_addresses(self):
        return Storefront(self.client, self.context, '/account/address', self, {}, self.__build_name('account-addresses-page'))

    def go_to_account_profile(self):
        return Storefront(self.client, self.context, '/account/profile', self, {}, self.__build_name('account-profile-page'))

    def go_to_account_orders(self):
        return Storefront(self.client, self.context, '/account/order', self, {}, self.__build_name('account-orders-page'))

    def go_to_account_create_address_form(self):
        return Storefront(self.client, self.context, '/account/address/create', self, {}, self.__build_name('account-create-address-page'))

    def go_to_account_payment(self):
        return Storefront(self.client, self.context, '/account/payment', self, {}, self.__build_name('account-payment-page'))

    def register(self, guest = 0):
        register = {
            'redirectTo': 'frontend.account.home.page',
            'salutationId': self.context.register['salutationId'],
            'firstName': 'Firstname',
            'lastName': 'Lastname',
            'email': 'user-' + str(uuid.uuid4()).replace('-', '') + '@example.com',
            'password': 'shopware',
            'billingAddress[street]': 'Test street',
            'billingAddress[zipcode]': '11111',
            'billingAddress[city]': 'Test city',
            'billingAddress[countryId]': self.context.register['countryId']
        }

        if guest == 1:
            register['guest'] = 1

        self.client.post('/account/register', data=register, name=self.__build_name('register'))
        self.__wait()

        if guest == 0:
            return self.go_to_account()

        return self.go_to_home()

    ### listing helpers ###
    def select_sorting(self):
        params = self.params
        params['order'] = random.choice(['name-desc', 'price-asc', 'price-desc'])
        params['p'] = 1

        return Storefront(self.client, self.context, self.url, self, params, self.name)

    def add_manufacturer_filter(self):
        params = self.params
        params['p'] = 1

        filters = self.__get_filters(self.response)

        if len(filters['manufacturers']) < 1:
            return Storefront(self.client, self.context, self.url, self, params, self.name)

        manufacturer = random.choice(filters['manufacturers'])
        if 'manufacturer' in params:
            params['manufacturer'] = params['manufacturer'] + '|' + manufacturer
        else:
            params['manufacturer'] = manufacturer

        return Storefront(self.client, self.context, self.url, self, params, self.name)

    def add_property_filter(self):
        params = self.params
        params['p'] = 1

        filters = self.__get_filters(self.response)

        if len(filters['properties']) < 1:
            return Storefront(self.client, self.context, self.url, self, params, self.name)

        manufacturer = random.choice(filters['properties'])
        if 'properties' in params:
            params['properties'] = params['properties'] + '|' + manufacturer
        else:
            params['properties'] = manufacturer

        return Storefront(self.client, self.context, self.url, self, params, self.name)

    def add_price_filter(self):
        params = self.params
        params['p'] = 1

        filters = self.__get_filters(self.response)

        if filters['max-price'] == None:
            return Storefront(self.client, self.context, self.url, self, params, self.name)

        price = filters['max-price']

        params['min-price'] = round(random.uniform(price / 20, price / 1.2), 2)

        return Storefront(self.client, self.context, self.url, self, params, self.name)

    # short hands
    def instant_order(self):
        page = self.go_to_cart()
        page = page.go_to_confirm()
        return page.make_order()

    def view_products(self, count = 2):
        for x in range(count):
            self.go_to_product()
            self.__wait([5, 15])
            self.refresh()

        return self

    def browse_account(self):
        page = self.go_to_account()
        self.go_to_account_addresses()
        self.go_to_account_profile()
        self.go_to_account_orders()
        self.go_to_account_create_address_form()
        self.go_to_account_payment()

        return page

    def add_products_to_cart(self, count = 2):
       for x in range(count):
           self.__add_product_to_cart()

       return self.go_to_cart()

    def __add_product_to_cart(self):
        number = random.choice(self.context.numbers)

        self.client.post('/checkout/product/add-by-number', name=self.__build_name('add-product'), data={
            'redirectTo': 'frontend.checkout.cart.page',
            'number': number
        })
        self.__offcanvas_cart()

    def __ajax_requests(self):
        self.client.get('/widgets/checkout/info', name=self.__build_name('ajax-cart-widget'))

    def __offcanvas_cart(self):
        self.client.get('/checkout/offcanvas', name=self.__build_name('ajax-cart-offcanvas'))

    def __do_request(self):
        self.response = self.client.get(self.url, params=self.params, name=self.__build_name(self.name))
        self.__ajax_requests()
        self.__wait()

    def __get_filters(self, response):
        content = '' if response.content == None else response.content
        soup = BeautifulSoup(content, 'html.parser')

        price = None
        if soup.find('input', { 'name': 'min-price' }):
            price = float(soup.find('input', { 'name': 'min-price' })['max'])

        return {
            'manufacturers': self.__collect_inputs('filter-multi-select-manufacturer', soup),
            'properties': self.__collect_inputs('filter-multi-select-properties', soup),
            'max-price': price
        }

    def __wait(self, seconds = None):
        if self.context.wait == False:
            return

        if self.context.wait == None:
            return

        wait = self.context.wait

        if seconds != None:
            wait = seconds

        seconds = random.randint(wait[0], wait[1])

        time.sleep(seconds)

    def __collect_inputs(self, css_class, soup):
        filters = []
        element = soup.find('div', class_='filter-multi-select-properties')
        if element == None:
            return filters

        for input in element.find_all('input'):
            filters.append(input['value'])

        return filters

    def __build_name(self, name):
        return name if self.context.aggregate == True else None
