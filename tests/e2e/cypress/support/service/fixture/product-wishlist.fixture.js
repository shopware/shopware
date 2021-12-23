const StoreFixtureService = require('@shopware-ag/e2e-testsuite-platform/cypress/support/service/saleschannel/fixture.service');

class productWishlistFixture extends StoreFixtureService{
    setProductWishlist(productId, customer) {
        return this.getClientId()
            .then((result) => {
                this.apiClient.setAccessKey(result);
            })
            .then(() => {
                return this.apiClient.post(`/account/login`, JSON.stringify({
                    username: customer.username,
                    password: customer.password
                }));
            })
            .then((response) => {
                return this.apiClient.setContextToken(response.data.contextToken);
            })
            .then(() => {
                return this.apiClient.post(`/customer/wishlist/add/${productId}`);
            })
            .catch((err) => {
                console.log('err :', err);
            });
    }
}

module.exports = productWishlistFixture;

