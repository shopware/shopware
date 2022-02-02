import os
import sys
import uuid
from locust import FastHttpUser, task, between, constant
from bs4 import BeautifulSoup

sys.path.append(os.path.dirname(__file__) + '/src')
from storefront import Storefront
from context import Context
from api import Api

context = Context()

class Admin(FastHttpUser):
    id = None
    weight = 1

    @task
    def stock_updates(self):
        # fixed_count locust config is broken
        if (not self.allowed()):
            return

        api = Api(self.client, context)
        api.update_stock()

    @task
    def price_updates(self):
        # fixed_count locust config is broken
        if (not self.allowed()):
            return

        api = Api(self.client, context)
        api.update_prices()

    def allowed(self):
        if (self.id == None):
            self.id = str(uuid.uuid4())

        if (self.id in context.admin_ids):
            return True

        if (len(context.admin_ids) < context.max_api_users):
            context.admin_ids.append(self.id)
            return True

        return False

class Customer(FastHttpUser):
    weight=5

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
        page = page.go_to_account_orders()

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
        page = page.go_to_account()
        page = page.go_to_account_orders()

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
