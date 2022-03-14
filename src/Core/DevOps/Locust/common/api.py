import random
import json
import uuid
import time
import requests
from locust.exception import RescheduleTask

class Api:
    context: None

    def __init__(self, client, context):
        self.client = client
        self.context = context
        self.headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'single-operation': 'true',
            'Authorization': 'Bearer ' + self.context.token,
            'indexing-skip': []
        }

        self.options = [
            'product.inheritance',
            'product.stock',
            'product.cheapest-price',
            'product.variant-listing',
            'product.child-count',
            'product.many-to-many-id-field',
            'product.category-denormalizer',
            'product.rating-average',
            'product.stream',
            'product.search-keyword',
            'category.seo-url',
            'product.seo-url',
            'landing_page.seo-url',
        ]

        if self.context.indexing_behavior:
            self.headers['indexing-behavior'] = self.context.indexing_behavior

    def __get_headers():
        return  {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'single-operation': 'true',
            'Authorization': 'Bearer ' + self.context.token,
            'indexing-skip': []
        }

    def update_stock(self):
        updates = []

        ids = self.__get_ids(25)
        for id in ids:
            updates.append({ 'id': id, 'stock': random.randint(0, 100) })

        operations = [
            { 'key': 'stock-update', 'action': 'upsert', 'entity': 'product', 'payload': updates }
        ]

        headers = self.headers
        headers['indexing-skip'] = self.__define_updaters([
            'product.inheritance',
            'product.stock',
        ])

        self._sync(operations, headers, '_api-stock-update')

    def update_prices(self):
        updates = []

        ids = self.__get_ids(25)
        for id in ids:
            updates.append({
                'id': id,
                'price': [
                    { 'currencyId': 'b7d2554b0ce847cd82f3ac9bd1c0dfca', 'gross': random.randint(100, 1000), 'net': random.randint(100, 1000), 'linked': False },
                ]
            })

        operations = [
            { 'key': 'price-update', 'action': 'upsert', 'entity': 'product', 'payload': updates }
        ]

        headers = self.headers
        headers['indexing-skip'] = self.__define_updaters([
            'product.cheapest-price',
        ])

        self._sync(operations, headers, '_api-price-update')

    def _sync(self, operations, headers, name):
        with self.client.post(self.context.url + '/api/_action/sync', json=operations, headers=headers, name=name,catch_response=True) as response:
            if response.status_code in [200, 204]:
                response.success()
                return

            if response.status_code == 401:
                self.context.refresh_token()
                return

            raise ValueError('Sync error: ' + str(response.status_code) + ' ' + response.text)

    def __define_updaters(self, excludes):
        skips = []
        for option in self.options:
            if option not in excludes:
                skips.append(option)

        return ','.join(skips)

    def __get_ids(self, count):
        ids = []

        while len(ids) < count:
            id = random.choice(self.context.product_ids)
            if id not in ids:
                ids.append(id)

        return ids
