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

