import Fixture from '../service/administration/fixture.service';

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
            return win.localStorage.getItem('bearerAuth');
        });
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
    const fixture = new Fixture();

    return fixture.search(data.endpoint, {
        field: data.data.field,
        type: 'equals',
        value: data.data.value
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
 * Handling API requests
 * @memberOf Cypress.Chainable#
 * @name clearCacheAdminApi
 * @function
 */
Cypress.Commands.add('clearCacheAdminApi', (method, url) => {
    return cy.authenticate().then((result) => {
        const requestConfig = {
            headers: {
                Authorization: `Bearer ${result.access}`
            },
            method: method,
            url: url
        };
        return cy.request(requestConfig);
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
