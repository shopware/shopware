import os
import sys
import time
from locust import FastHttpUser, task, between, constant,tag
from bs4 import BeautifulSoup

sys.path.append(os.path.dirname(__file__) + '/..')

from common.storefront import Storefront
from common.context import Context
from common.api import Api

context = Context()

class Erp(FastHttpUser):
    fixed_count = 1

    def on_start(self):
        self.api = Api(self.client, context)

    @task
    def call_api(self):
        if (context.erp == False):
            return

        self.api.update_prices()
        self.api.update_stock()

class Visitor(FastHttpUser):
    wait_time = between(2, 5)
    weight = 10

    @task(3)
    def listing(self):
        page = Storefront(self.client, context)
        page = page.go_to_listing()
        page = page.view_products(2)

        page = page.go_to_next_page()
        page = page.view_products(3)

    @task(1)
    def search(self):
        page = Storefront(self.client, context)
        page = page.do_search()
        page = page.view_products(2)

        page = page.go_to_next_page()
        page = page.view_products(2)
        page = page.go_to_next_page()

        page = page.add_manufacturer_filter()
        page = page.select_sorting()
        page = page.view_products(3)

class Surfer(FastHttpUser):
    wait_time = between(2, 5)
    weight = 6

    @task
    def surf(self):
        page = Storefront(self.client, context)

        # search products over listings
        page = page.go_to_listing()

        # take a look to the first two products
        page = page.view_products(2)
        page = page.go_to_next_page()

        # open two different product pages
        page = page.view_products(2)

        # sort listing and use properties to filter
        page = page.select_sorting()
        page = page.add_property_filter()

        page = page.view_products(1)
        page = page.go_to_next_page()

        # switch to search to find products
        page = page.do_search()
        page = page.view_products(2)

        # use property filter to find products
        page = page.add_property_filter()

        # take a look to the top three hits
        page = page.view_products(3)
        page = page.go_to_next_page()

class SurfWithOrder(FastHttpUser):
    wait_time = between(2, 5)
    weight = 6

    @task
    def surf(self):
        page = Storefront(self.client, context)
        page = page.register()      #instead of login, we register
        page = page.browse_account()

        # search products over listings
        page = page.go_to_listing()

        # take a look to the first two products
        page = page.view_products(2)
        page = page.add_product_to_cart()
        page = page.go_to_next_page()

        # open two different product pages
        page = page.view_products(2)
        page = page.add_product_to_cart()

        # sort listing and use properties to filter
        page = page.select_sorting()
        page = page.add_property_filter()
        page = page.view_products(1)
        page = page.go_to_next_page()
        page = page.add_product_to_cart()
        page = page.instant_order()

        # switch to search to find products
        page = page.do_search()
        page = page.view_products(2)

        # use property filter to find products
        page = page.add_property_filter()

        # take a look to the top three hits
        page = page.view_products(3)
        page = page.add_product_to_cart()
        page = page.add_product_to_cart()
        page = page.go_to_next_page()

        page = page.view_products(2)
        page = page.add_product_to_cart()
        page = page.add_product_to_cart()
        page = page.add_product_to_cart()

        page = page.instant_order()
        page = page.logout()

class FastOrder(FastHttpUser):
    wait_time = between(2, 5)
    weight = 4
    def on_start(self):
        self.page = Storefront(self.client, context)
        self.page.register()

    @task
    def order(self):
        self.page.add_product_to_cart()
        self.page.add_product_to_cart()
        self.page.add_product_to_cart()
        self.page.add_product_to_cart()
        self.page.instant_order()
