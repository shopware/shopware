import requests
import csv
import os
import json
from os.path import exists

class Context:
    keywords: []
    listings: []
    product_urls: []
    product_ids: []
    products: []
    numbers: []
    register: {}
    aggregate: False
    wait: False
    erp: True
    host: None
    indexing_behavior: None

    def __init__(self):
        self.env = self.__get_env()
        self.url = self.env['url']
        self.token = self.get_token()
        self.aggregate = self.env['aggregate']
        self.wait = self.env['wait']
        self.keywords = self.__initKeywords()
        self.listings = self.__initListings()
        self.product_urls = self.__initProductsUrls()
        self.products = self.__initProducts()
        self.numbers = self.__column(self.products, 'productNumber')
        self.product_ids = self.__column(self.products, 'id')
        self.register = self.__initRegister()
        self.indexing_behavior = None
        self.admin_ids = []
        self.erp = self.env['erp']

        if (self.env['indexing_behavior'] != False):
            self.indexing_behavior = self.env['indexing_behavior']

    def __initListings(self):
        return self.__get_json_file('/../fixtures/listing_urls.json')

    def __initProductsUrls(self):
        return self.__get_json_file('/../fixtures/product_urls.json')

    def __initProducts(self):
        return self.__get_json_file('/../fixtures/products.json')

    def __initKeywords(self):
        return self.__get_json_file('/../fixtures/keywords.json')

    def refresh_token(self):
        self.token = self.get_token()

    def get_token(self):
        response = requests.post(self.env['url'] + '/api/oauth/token', data=self.env['oauth'])

        if response.status_code == 200:
            return response.json()['access_token']
        else:
            raise Exception('Error: ' + response.content)

    def __initRegister(self):
        return self.__get_json_file('/../fixtures/sales_channel.json')

    def __get_env(self):
        env = self.__get_json_file('/../env.json')
        dist = self.__get_json_file('/../env.dist.json')

        dist.update(env)
        return dist

    def __column(self, matrix, i):
        all = []
        for row in matrix:
            all.append(row[i])
        return all

    def __get_json_file(self, file):
        path = os.path.dirname(os.path.realpath(__file__)) + file

        if (exists(path) == False):
            return {}

        with open(path) as file:
            return json.load(file)
