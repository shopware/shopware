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

// Function to fetch Storefront sales channel ID and navigation category ID by translation
const getSalesChannelData = () => {
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
                    ]
                })
            }
        }, (err, res) => {
            if (err) {
                console.error('Error fetching sales channel:', err);
                reject(err);
            } else {
                const data = res.json();
                const salesChannel = data.data?.[0];
                if (!salesChannel) {
                    console.error('Storefront sales channel not found');
                    reject(new Error('Storefront sales channel not found'));
                } else {
                    console.log('Fetched Storefront sales channel ID:', salesChannel.id);
                    console.log('Fetched Navigation category ID:', salesChannel.attributes.navigationCategoryId);
                    resolve({
                        salesChannelId: salesChannel.id,
                        navigationCategoryId: salesChannel.attributes.navigationCategoryId
                    });
                }
            }
        });
    });
};

// First fetch admin token, then fetch all required IDs
getAdminToken()
    .then(() => {
        return Promise.all([
            getSalesChannelData(),
            getFirstId('tax', [{ type: 'equals', field: 'taxRate', value: 19 }])
        ]);
    })
    .then(([salesChannelData, taxId]) => {
        // Store IDs as collection variables
        pm.collectionVariables.set('salesChannelId', salesChannelData.salesChannelId);
        pm.collectionVariables.set('navigationCategoryId', salesChannelData.navigationCategoryId);
        pm.collectionVariables.set('taxId', taxId);

        console.log('✓ All IDs fetched successfully');
        console.log('Sales Channel:', salesChannelData.salesChannelId);
        console.log('Navigation Category:', salesChannelData.navigationCategoryId);
        console.log('Tax:', taxId);
    })
    .catch(err => {
        console.error('Failed to fetch IDs:', err);
        throw err;
    });
