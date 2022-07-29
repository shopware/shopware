import os
import sys
sys.path.append(os.path.dirname(__file__) + '/..')

from common.store_api import StoreApi
from common.context import Context
import random
from locust import FastHttpUser, task

# Optional dependency
try:
    import locust_plugins
except ImportError:
    pass


context = Context()


class Tester(FastHttpUser):
    def on_start(self):
        self.api = StoreApi(self.client, context)

    @task
    def call_api(self):
        self.api.home()
        self.api.navigation()
        self.api.navigation(random.choice(context.category_ids))
        self.api.footer()
        self.api.service()
        self.api.listing()
        self.api.shipping_methods()
        self.api.payment_methods()
        self.api.languages()
        self.api.currencies()
        self.api.salutations()
        self.api.countries()
        self.api.search()
        self.api.suggest()
        self.api.product()
        self.api.add_product_to_cart()
        self.api.add_product_to_cart()
        self.api.add_product_to_cart()
        self.api.register()
        self.api.cart()
        self.api.order()
