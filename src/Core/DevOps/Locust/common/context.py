import requests
import os
import json
from os.path import exists


class Context:

    def __init__(self):
        self.env = self.__get_env()
        self.url = self.env['url']
        self.token = self.get_token()
        self.aggregate = self.env['aggregate']
        self.wait = self.env['wait']
        self.track_ajax_requests = self.env['track_ajax_requests']
        self.keywords = self.__get_json_file('/../fixtures/keywords.json')
        self.listings = self.__get_json_file('/../fixtures/listing_urls.json')
        self.product_urls = self.__get_json_file('/../fixtures/product_urls.json')
        self.products = self.__get_json_file('/../fixtures/products.json')
        self.advertisements = self.__get_json_file('/../fixtures/advertisements.json')
        self.sales_channel = self.__get_json_file('/../fixtures/sales_channel.json')
        self.imports = self.__get_json_file('/../fixtures/imports.json')
        self.category_ids = self.__get_json_file('/../fixtures/categories.json')

        self.numbers = self.__column(self.products, 'productNumber')
        self.product_ids = self.__column(self.products, 'id')
        self.indexing_behavior = None
        self.admin_ids = []
        self.erp = self.env['erp']

        if (self.env['indexing_behavior'] != False):
            self.indexing_behavior = self.env['indexing_behavior']

    def refresh_token(self):
        self.token = self.get_token()

    def get_token(self):
        response = requests.post(self.env['url'] + '/api/oauth/token', data=self.env['oauth'])

        if response.status_code == 200:
            return response.json()['access_token']
        else:
            raise Exception('Error: ' + response.content)

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
