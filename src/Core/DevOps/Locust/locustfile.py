import requests
import time
import csv
import os
import random
import uuid
import json
from locust import HttpUser, task, between, constant

class Purchaser(HttpUser):
    weight = 10
    wait_time = constant(15)
    countryId = 1
    salutationId = 1

    def on_start(self):
        self.initRegister()
        self.register()

    def initRegister(self):
        path = os.path.dirname(os.path.realpath(__file__)) + '/fixtures/register.json'
        with open(path) as file:
            data = json.load(file)
            self.countryId = data['countryId']
            self.salutationId = data['salutationId']

    def register(self):
        register = {
            'redirectTo': 'frontend.account.home.page',
            'salutationId': self.salutationId,
            'firstName': 'Firstname',
            'lastName': 'Lastname',
            'email': 'user-' + str(uuid.uuid4()).replace('-', '') + '@example.com',
            'password': 'shopware',
            'billingAddress[street]': 'Test street',
            'billingAddress[zipcode]': '11111',
            'billingAddress[city]': 'Test city',
            'billingAddress[countryId]': self.countryId
        }

        self.client.post('/account/register', data=register, name='register')

    def addProduct(self):
        number = random.choice(numbers)

        self.client.post('/checkout/product/add-by-number', name='add-product', data={
            'redirectTo': 'frontend.checkout.cart.page',
            'number': number
        })

    @task
    def order(self):
        url = random.choice(listings)
        self.client.get(url, name='listing-page-logged-in')

        self.client.get('/widgets/checkout/info', name='cart-widget')

        count = random.randint(1, 5)
        for i in range(1,count+1):
            self.addProduct()

        self.client.get('/checkout/cart', name='cart-page')

        self.client.get('/checkout/confirm', name='confirm-page')

        self.client.post('/checkout/order', name='order', data={
            'tos': 'on'
        })

class Surfer(HttpUser):
    weight = 10
    wait_time = constant(2)

    @task(10)
    def listing_page(self):
        url = random.choice(listings)
        self.client.get(url, name='listing-page')
        self.client.get('/widgets/checkout/info', name='cart-widget')

    @task(4)
    def detail_page(self):
        url = random.choice(details)
        self.client.get(url, name='detail-page')
        self.client.get('/widgets/checkout/info', name='cart-widget')


listings = []
details = []
numbers = []

def initListings():
    path = os.path.dirname(os.path.realpath(__file__)) + '/fixtures/listing_urls.csv'
    with open(path) as file:
        reader = csv.reader(file, delimiter=',')
        for row in reader:
            listings.append(row[0])

def initProducts():
    path = os.path.dirname(os.path.realpath(__file__)) + '/fixtures/product_urls.csv'
    with open(path) as file:
        reader = csv.reader(file, delimiter=',')
        for row in reader:
            details.append(row[0])

def initNumbers():
    path = os.path.dirname(os.path.realpath(__file__)) + '/fixtures/product_numbers.csv'
    with open(path) as file:
        reader = csv.reader(file, delimiter=',')
        for row in reader:
            numbers.append(row[0])

initListings()
initProducts()
initNumbers()

# class Importer(HttpUser):
#     weight = 1
#     wait_time = constant(60)
#     salesChannelId = 0
#     taxId = 0
#     currencyId = 0
#     categoryIds = []
#     productIds = []
#
#     def on_start(self):
#         self.initImporter()
#
#     @task
#     def stock_update(self):
#         products = []
#
#         count = random.randint(20, 50)
#
#         for i in range(1,count+1):
#             products.append({
#                 'id': random.choice(self.productIds),
#                 'stock': random.randint(5, 2000)
#             })
#
#         response = requests.post(
#             self.environment.host + '/api/_action/sync',
#             json={
#                 'import-products': {
#                     'entity': 'product',
#                     'action': 'upsert',
#                     'payload': products
#                 }
#             },
#             headers={
#                 'Authorization': 'Bearer ' + self.getAccessToken(),
#                 'single-operation': 'true'
#             }
#         )
#
#     @task
#     def product_import(self):
#         response = requests.post(
#             self.environment.host + '/api/_action/sync',
#             json={
#                 "import-products": {
#                     "entity": "product",
#                     "action": "upsert",
#                     "payload": self.buildProducts()
#                 }
#             },
#             headers={
#                 'Authorization': 'Bearer ' + self.getAccessToken(),
#                 'single-operation': 'true'
#             }
#         )
#
#     def initImporter(self):
#         path = os.path.dirname(os.path.realpath(__file__)) + '/fixtures/importer.json'
#
#         with open(path) as file:
#             data = json.load(file)
#             self.currencyId = data['currencyId']
#             self.salesChannelId = data['salesChannelId']
#             self.productIds = data['productIds']
#             self.categoryIds = data['categoryIds']
#             self.taxId = data['taxId']
#
#     def getAccessToken(self):
#         response = self.client.post(self.environment.host + '/api/oauth/token', json={
#             "client_id": "administration",
#             "grant_type": "password",
#             "scopes": "write",
#             "username": "admin",
#             "password": "shopware"
#         })
#
#         data = response.json()
#
#         return data['access_token']
#
#     def buildProducts(self):
#         count = random.randint(20, 50)
#
#         products = []
#
#         for i in range(1,count+1):
#             products.append({
#                 'name': 'Example',
#                 'active': True,
#                 'visibilities': [
#                     { 'salesChannelId': self.salesChannelId, 'visibility': 30 }
#                 ],
#                 'taxId': self.taxId,
#                 'productNumber': str(uuid.uuid4()).replace('-', ''),
#                 'price': [
#                     {
#                         'currencyId': self.currencyId,
#                         'gross': random.randint(100, 400),
#                         'net': random.randint(100, 400),
#                         'linked': True
#                     }
#                 ],
#                 'stock': random.randint(5, 2000),
#                 'categories': [
#                     { 'id': random.choice(self.categoryIds) }
#                 ]
#             })
#
#         return products
