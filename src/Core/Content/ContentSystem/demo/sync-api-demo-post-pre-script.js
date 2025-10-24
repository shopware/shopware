pm.environment.set("baseUrl", "http://localhost:8000");

// Function to fetch admin token via OAuth
const getAdminToken = () => {
    return new Promise((resolve, reject) => {
        pm.sendRequest({
            url: `${pm.environment.get('baseUrl')}/api/oauth/token`,
            method: 'POST',
            header: {
                'Content-Type': 'application/json'
            },
            body: {
                mode: 'raw',
                raw: JSON.stringify({
                    grant_type: 'password',
                    client_id: 'administration',
                    username: 'admin',
                    password: 'shopware'
                })
            }
        }, (err, res) => {
            if (err) {
                console.error('Error fetching admin token:', err);
                reject(err);
            } else {
                const data = res.json();
                const token = data.access_token;
                if (!token) {
                    console.error('No access token in response');
                    reject(new Error('No access token received'));
                } else {
                    console.log('✓ Admin token fetched successfully');
                    pm.environment.set('adminToken', token);
                    resolve(token);
                }
            }
        });
    });
};

const getFirstId = (endpoint, filters) => {
    return new Promise((resolve, reject) => {
        pm.sendRequest({
            url: `${pm.environment.get('baseUrl')}/api/search/${endpoint}`,
            method: 'POST',
            header: {
                'Authorization': `Bearer ${pm.environment.get('adminToken')}`,
                'Content-Type': 'application/json'
            },
            body: {
                mode: 'raw',
                raw: JSON.stringify({
                    limit: 1,
                    filter: filters,
                    includes: {
                        [endpoint.replace('-', '_')]: ['id']
                    }
                })
            }
        }, (err, res) => {
            if (err) {
                console.error(`Error fetching ${endpoint}:`, err);
                reject(err);
            } else {
                const data = res.json();
                const id = data.data?.[0]?.id;
                if (!id) {
                    console.error(`No ${endpoint} found matching filters`);
                    reject(new Error(`No ${endpoint} found`));
                } else {
                    console.log(`Fetched ${endpoint} ID:`, id);
                    resolve(id);
                }
            }
        });
    });
};

// Function to fetch Storefront sales channel ID by translation
const getSalesChannelId = () => {
    return new Promise((resolve, reject) => {
        pm.sendRequest({
            url: `${pm.environment.get('baseUrl')}/api/search/sales-channel`,
            method: 'POST',
            header: {
                'Authorization': `Bearer ${pm.environment.get('adminToken')}`,
                'Content-Type': 'application/json'
            },
            body: {
                mode: 'raw',
                raw: JSON.stringify({
                    limit: 1,
                    filter: [
                        { type: 'equals', field: 'active', value: true },
                        { type: 'equals', field: 'translations.name', value: 'Storefront' }
                    ],
                    includes: {
                        sales_channel: ['id']
                    }
                })
            }
        }, (err, res) => {
            if (err) {
                console.error('Error fetching sales channel:', err);
                reject(err);
            } else {
                const data = res.json();
                const id = data.data?.[0]?.id;
                if (!id) {
                    console.error('Storefront sales channel not found');
                    reject(new Error('Storefront sales channel not found'));
                } else {
                    console.log('Fetched Storefront sales channel ID:', id);
                    resolve(id);
                }
            }
        });
    });
};

// First fetch admin token, then fetch all required IDs
getAdminToken()
    .then(() => {
        return Promise.all([
            getSalesChannelId(),
            getFirstId('payment-method', [{ type: 'equals', field: 'active', value: true }]),
            getFirstId('shipping-method', [{ type: 'equals', field: 'active', value: true }]),
            getFirstId('country', [{ type: 'equals', field: 'active', value: true }]),
            getFirstId('snippet-set', [{ type: 'equals', field: 'iso', value: 'en-GB' }]),
            getFirstId('tax', [{ type: 'equals', field: 'taxRate', value: 19 }])
        ]);
    })
    .then(([salesChannelId, paymentId, shippingId, countryId, snippetSetId, taxId]) => {
        // Store IDs as collection variables
        pm.collectionVariables.set('salesChannelId', salesChannelId);
        pm.collectionVariables.set('paymentMethodId', paymentId);
        pm.collectionVariables.set('shippingMethodId', shippingId);
        pm.collectionVariables.set('countryId', countryId);
        pm.collectionVariables.set('snippetSetId', snippetSetId);
        pm.collectionVariables.set('taxId', taxId);

        console.log('✓ All IDs fetched successfully');
        console.log('Sales Channel:', salesChannelId);
        console.log('Payment Method:', paymentId);
        console.log('Shipping Method:', shippingId);
        console.log('Country:', countryId);
        console.log('Snippet Set:', snippetSetId);
        console.log('Tax:', taxId);
    })
    .catch(err => {
        console.error('Failed to fetch IDs:', err);
        throw err;
    });
