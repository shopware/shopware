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

class SearchTester(FastHttpUser):
    def on_start(self):
        self.api = StoreApi(self.client, context)

    @task
    def search(self):
        self.api.search()

    @task
    def suggest(self):
        self.api.suggest()

    @task
    def searchWithSorting(self):
        self.api.searchWithSorting()

