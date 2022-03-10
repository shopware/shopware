import os
import sys
import time
from locust import FastHttpUser, task, between, constant
from bs4 import BeautifulSoup

sys.path.append(os.path.dirname(__file__) + '/..')

from common.storefront import Storefront
from common.context import Context
from common.api import Api

context = Context()

class Erp(FastHttpUser):
    fixed_count=1
    def on_start(self):
        self.api = Api(self.client, context)

    @task
    def call_api(self):
        self.api.update_prices()
        self.api.update_stock()

class Customer(FastHttpUser):
    wait_time = between(2, 10)

    @task(4)
    def short_time_listing_visitor(self):
        page = Storefront(self.client, context)
        page = page.go_to_listing()
        page = page.view_products(2)

        page = page.go_to_next_page()
        page = page.view_products(3)

    @task(4)
    def short_time_search_visitor(self):
        page = Storefront(self.client, context)
        page = page.do_search()
        page = page.view_products(2)

        page = page.go_to_next_page()
        page = page.view_products(2)
        page = page.go_to_next_page()

        page = page.add_manufacturer_filter()
        page = page.select_sorting()
        page = page.view_products(3)

    @task(3)
    def long_time_visitor(self):
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

    @task(3)
    def short_time_buyer(self):
        page = Storefront(self.client, context)
        page = page.register()       #instead of login, we register
        page = page.browse_account()

        page = page.go_to_listing()
        page = page.view_products(2)
        page = page.add_product_to_cart()
        page = page.add_product_to_cart()
        page = page.instant_order()
        page = page.logout()

    @task(2)
    def long_time_buyer(self):
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
