import os
import sys
import time
from locust import FastHttpUser, task, between, constant,tag
from bs4 import BeautifulSoup
import locust_plugins

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
        page.go_to_listing()
        page.view_products(2)

        page.go_to_listing()
        if (context.allow_filter == True):
            page.add_manufacturer_filter()

        page.select_sorting()
        page.go_to_next_page()
        page.view_products(3)

        page.go_to_listing()
        if (context.allow_filter == True):
            page.add_property_filter()

        page.go_to_next_page()
        page.view_products(2)

    @task(1)
    def search(self):
        page = Storefront(self.client, context)
        page.do_search()
        page.view_products(2)

        page.go_to_next_page()
        page.view_products(2)
        page.go_to_next_page()

        if (context.allow_filter == True):
            page.add_manufacturer_filter()

        page.select_sorting()
        page.view_products(3)

class Surfer(FastHttpUser):
    wait_time = between(2, 5)
    weight = 6

    @task
    def surf(self):
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
        if (context.allow_filter == True):
            page.add_property_filter()

        page.view_products(1)
        page.go_to_next_page()

        # switch to search to find products
        page.do_search()
        page.view_products(2)

        # use property filter to find products
        if (context.allow_filter == True):
            page.add_property_filter()

        # take a look to the top three hits
        page.view_products(3)
        page.go_to_next_page()

class SurfWithOrder(FastHttpUser):
    wait_time = between(2, 5)
    weight = 6

    @task
    def surf(self):
        page = Storefront(self.client, context)
        page.register()      #instead of login, we register
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
        if (context.allow_filter == True):
            page.add_property_filter()

        page.view_products(1)
        page.go_to_next_page()
        page.add_product_to_cart()
        page.instant_order()

        # switch to search to find products
        page.do_search()
        page.view_products(2)

        if (context.allow_filter == True):
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

class Nvidia(FastHttpUser):
    weight = 2
    @task
    def follow_advertisement(self):
        page = Storefront(self.client, context)
        page.register()
        page.add_advertisement()
        page.instant_order()
        page.logout()
