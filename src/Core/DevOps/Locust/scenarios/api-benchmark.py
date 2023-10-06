import os
import sys
sys.path.append(os.path.dirname(__file__) + '/..')

from common.api import Api
from common.context import Context
from locust import FastHttpUser, task

# Optional dependency
try:
    import locust_plugins
except ImportError:
    pass


context = Context()


class Imports(FastHttpUser):
    def on_start(self):
        self.api = Api(self.client, context)

    @task
    def call_api(self):
        self.api.import_products(10)


class Stocks(FastHttpUser):
    def on_start(self):
        self.api = Api(self.client, context)

    @task
    def call_api(self):
        self.api.update_stock(25)


class Prices(FastHttpUser):
    def on_start(self):
        self.api = Api(self.client, context)

    @task
    def call_api(self):
        self.api.update_prices(15)

