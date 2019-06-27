/**
 * Authenticate towards the Shopware API
 * @memberOf Cypress.Chainable#
 * @name authenticate
 * @function
 */
Cypress.Commands.add('authenticate', () => {
    return cy.request(
        'POST',
        '/api/oauth/token',
        {
            grant_type: Cypress.env('grant') ? Cypress.env('grant') : 'password',
            client_id: Cypress.env('client_id') ? Cypress.env('client_id') : 'administration',
            scopes: Cypress.env('scope') ? Cypress.env('scope') : 'write',
            username: Cypress.env('username') ? Cypress.env('user') : 'admin',
            password: Cypress.env('password') ? Cypress.env('pass') : 'shopware'
        }
    ).then((responseData) => {
        return {
            access: responseData.body.access_token,
            refresh: responseData.body.refresh_token,
            expiry: Math.round(+new Date() / 1000) + responseData.body.expires_in
        };
    });
});

/**
 * Switches administration UI locale to EN_GB
 * @memberOf Cypress.Chainable#
 * @name setLocaleToEnGb
 * @function
 */
Cypress.Commands.add('setLocaleToEnGb', () => {
    cy.authenticate().then(() => {
        return cy.window().then((win) => {
            win.localStorage.setItem('sw-admin-locale', Cypress.env('locale'));
        });
    });
});

/**
 * Logs in silently using Shopware API
 * @memberOf Cypress.Chainable#
 * @name loginViaApi
 * @function
 */
Cypress.Commands.add('loginViaApi', () => {
    return cy.authenticate().then((result) => {
        return cy.window().then((win) => {
            win.localStorage.setItem('bearerAuth', JSON.stringify(result));
            // Return bearer token
            return localStorage.getItem('bearerAuth');
        }, [result], (data) => {
            if (!data.value) {
                cy.login('admin');
            }
        }).then(() => {
            cy.visit(Cypress.env('admin'));
        });
    });
});

/**
 * Handling API requests
 * @memberOf Cypress.Chainable#
 * @name requestAdminApi
 * @function
 */
Cypress.Commands.add('requestAdminApi', (method, url, requestData = {}) => {
    return cy.authenticate().then((result) => {
        const requestConfig = {
            headers: {
                Accept: 'application/vnd.api+json',
                Authorization: `Bearer ${result.access}`,
                'Content-Type': 'application/json'
            },
            method: method,
            url: url,
            qs: {
                response: true
            },
            body: requestData.data
        };
        return cy.request(requestConfig);
    }).then((response) => {
        if (response.body) {
            const responseBodyObj = response.body ? JSON.parse(response.body) : response;

            if (Array.isArray(responseBodyObj.data) && responseBodyObj.data.length <= 1) {
                return responseBodyObj.data[0];
            }
            return responseBodyObj.data;
        }
        return response;
    });
});

/**
 * Handling API requests origination from a search task
 * @memberOf Cypress.Chainable#
 * @name searchRequestAdminApi
 * @function
 */
Cypress.Commands.add('searchRequestAdminApi', (method, url, requestData = {}) => {
    return cy.authenticate().then((result) => {
        const requestConfig = {
            headers: {
                Accept: 'application/vnd.api+json',
                Authorization: `Bearer ${result.access}`,
                'Content-Type': 'application/json'
            },
            method: method,
            url: url,
            qs: {
                response: true
            },
            body: requestData
        };
        return cy.request(requestConfig);
    }).then((response) => {
        if (response.body) {
            const responseBodyObj = response;

            if (Array.isArray(responseBodyObj.data) && responseBodyObj.data.length <= 1) {
                return responseBodyObj.data[0];
            }
            return responseBodyObj.data;
        }
        return response;
    });
});

/**
 * Creates an entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name createViaAdminApi
 * @function
 * @param {Object} data - Necessary  for the API request
 */
Cypress.Commands.add('createViaAdminApi', (data) => {
    return cy.requestAdminApi(
        'POST',
        `${Cypress.env('apiPath')}/${data.endpoint}?response=true`,
        data
    ).then((responseData) => {
        return responseData;
    });
});

/**
 * Search for an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name searchViaAdminApi
 * @function
 * @param {Object} data - Necessary data for the API request
 */
Cypress.Commands.add('searchViaAdminApi', (data) => {
    const filters = {
        filter: [{
            field: data.data.field,
            type: 'equals',
            value: data.data.value
        }]
    };

    return cy.searchRequestAdminApi(
        'POST',
        `${Cypress.env('apiPath')}/search/${data.endpoint}`,
        filters
    ).then((responseData) => {
        return responseData.body.data[0];
    });
});

/**
 * Search for an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name deleteViaAdminApi
 * @function
 * @param {String} endpoint - API endpoint for the request
 * @param {String} id - Id of the entity to be deleted
 */
Cypress.Commands.add('deleteViaAdminApi', (endpoint, id) => {
    return cy.requestAdminApi('DELETE', `${Cypress.env('apiPath')}/${endpoint}/${id}`).then((responseData) => {
        return responseData;
    });
});

/**
 * Updates an existing entity using Shopware API at the given endpoint
 * @memberOf Cypress.Chainable#
 * @name updateViaAdminApi
 * @function
 * @param {String} endpoint - API endpoint for the request
 * @param {String} id - Id of the entity to be updated
 * @param {Object} data - Necessary data for the API request
 */
Cypress.Commands.add('updateViaAdminApi', (endpoint, id, data) => {
    return cy.requestAdminApi('PATCH', `${Cypress.env('apiPath')}/${endpoint}/${id}`, data).then((responseData) => {
        return responseData;
    });
});

