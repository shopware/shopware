import os
import sys
sys.path.append(os.path.dirname(__file__) + '/..')

from common.api import Api
from common.context import Context
from common.storefront import Storefront
from locust import FastHttpUser, task, between

# Optional dependency
try:
    import locust_plugins
except ImportError:
    pass


context = Context()


class Sync(FastHttpUser):
    fixed_count = 5

    def on_start(self):
        self.api = Api(self.client, context)

    @task
    def product_import(self):
        self.api.import_products(10)

    @task
    def stock_updates(self):
        self.api.update_stock(25)

    @task
    def price_updates(self):
        self.api.update_prices(15)


class Visitor(FastHttpUser):
    wait_time = between(2, 5)
    weight = 10

    @task(3)
    def listing(self):
        page = Storefront(self.client, context)

        # search products over listings
        page.go_to_listing()

        # take a look to the first two products
        page.view_products(2)
        page.go_to_next_page()

        # open two different product pages
        page.view_products(2)

        # sort listing and use properties to filter
        page.select_sorting()
        page.add_property_filter()

        page.view_products(1)
        page.go_to_next_page()

        # switch to search to find products
        page.do_search()
        page.view_products(2)

        # use property filter to find products
        page.add_property_filter()

        # take a look to the top three hits
        page.view_products(3)
        page.go_to_next_page()

    @task(2)
    def search(self):
        page = Storefront(self.client, context)
        page.do_search()
        page.view_products(2)

        page.go_to_next_page()
        page.view_products(2)
        page.go_to_next_page()

        page.add_manufacturer_filter()

        page.select_sorting()
        page.view_products(3)


class SurfWithOrder(FastHttpUser):
    wait_time = between(2, 5)
    weight = 6

    @task
    def surf(self):
        page = Storefront(self.client, context)
        page.register()  # instead of login, we register
        page.browse_account()

        # search products over listings
        page.go_to_listing()

        # take a look to the first two products
        page.view_products(2)
        page.add_product_to_cart()
        page.go_to_next_page()

        # open two different product pages
        page.view_products(2)
        page.add_product_to_cart()

        # sort listing and use properties to filter
        page.select_sorting()
        page.add_property_filter()

        page.view_products(1)
        page.go_to_next_page()
        page.add_product_to_cart()
        page.instant_order()

        # switch to search to find products
        page.do_search()
        page.view_products(2)

        # use property filter to find products
        page.add_property_filter()

        # take a look to the top three hits
        page.view_products(3)
        page.add_product_to_cart()
        page.add_product_to_cart()
        page.go_to_next_page()

        page.view_products(2)
        page.add_product_to_cart()
        page.add_product_to_cart()
        page.add_product_to_cart()

        page.instant_order()
        page.logout()


class FastOrder(FastHttpUser):
    weight = 4

    def on_start(self):
        self.page = Storefront(self.client, context)
        self.page.register()
        self.page.logout()

    @task
    def order(self):
        self.page.login()
        self.page.add_products_to_cart(3)
        self.page.instant_order()
        self.page.logout()
