from api import Api
import time

class ERP:
    def __init__(self, context):
        self.context = context
        self.api = Api(context)

    def run(self):
        while True:
            self.api.update_stock()
            time.sleep(10)
            self.api.update_prices()
            time.sleep(10)
            # refresh token
            self.context.token = self.context.get_token()
