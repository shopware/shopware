import os
import sys
import time
from locust import FastHttpUser, task, between, constant

sys.path.append(os.path.dirname(__file__) + '/..')
from common.storefront import Storefront
from common.context import Context

context = Context()

class Customer(FastHttpUser):
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
