import random
import uuid
from faker import Faker
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

    def import_products(self, count):
        products = []

        while len(products) < count:
            products.append(self.__generate_product())

        operations = [
            { 'key': 'product-import', 'action': 'upsert', 'entity': 'product', 'payload': products }
        ]

        self._sync(operations, self.headers, '_api-product-imports')

    def __generate_product(self):
        fake = Faker()

        return {
            'name': fake.name(),
            'description': fake.text(),
            'productNumber': str(uuid.uuid4()),
            'active': True,
            'price': [
                { 'currencyId': 'b7d2554b0ce847cd82f3ac9bd1c0dfca', 'gross': random.randint(100, 1000), 'net': random.randint(100, 1000), 'linked': False }
            ],
            'visibilities': [
                { 'salesChannelId': self.context.imports['salesChannelId'], 'visibility': 30 }
            ],
            'taxId': self.context.imports['taxId'],
            'stock': random.randint(1, 100),
            'isCloseout': random.choice([True, False]),
            'categories': random.sample(self.context.imports['categories'], 3),
            'properties': random.sample(self.context.imports['properties'], 3),
            'media': random.sample(self.context.imports['media'], 5)
        }

    def update_stock(self, count):
        updates = []

        ids = self.__get_ids(count)
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

    def update_prices(self, count):
        updates = []

        ids = self.__get_ids(count)
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
                raise RescheduleTask()

            text = response.json()

            response.failure('Sync error: ' + str(response.status_code) + ' ' + response.text)

    def __define_updaters(self, excludes):
        skips = []
        for option in self.options:
            if option not in excludes:
                skips.append(option)

        return ','.join(skips)

    def __get_ids(self, count):
        return random.sample(self.context.product_ids, count)
