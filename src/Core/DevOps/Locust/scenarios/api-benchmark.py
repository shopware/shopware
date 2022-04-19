import os
import sys
import time
from locust import FastHttpUser, task, between, constant,tag
from bs4 import BeautifulSoup
import locust_plugins

sys.path.append(os.path.dirname(__file__) + '/..')

from common.context import Context
from common.api import Api

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