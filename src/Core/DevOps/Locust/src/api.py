import random
import json
import uuid
import time
import requests

class Api:
    context: None

    def __init__(self, client, context):
        self.client = client
        self.context = context
        self.headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'single-operation': 'true',
            'Authorization': 'Bearer ' + self.context.token
        }

    def update_stock(self):
        updates = []

        ids = self.__get_ids(25)
        for id in ids:
            updates.append({ 'id': id, 'stock': random.randint(0, 100) })

        operations = [
            { 'key': 'stock-update', 'action': 'upsert', 'entity': 'product', 'payload': updates }
        ]

        response = self.client.post('/api/_action/sync', json=operations, headers=self.headers, name='_api-stock-update')

    def update_prices(self):
        updates = []

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

        response = self.client.post('/api/_action/sync', json=operations, headers=self.headers, name='_api-price-update')

    def __get_ids(self, count):
        ids = []

        while len(ids) < count:
            id = random.choice(self.context.product_ids)
            if id not in ids:
                ids.append(id)

        return ids
