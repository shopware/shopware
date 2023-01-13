// / <reference types="Cypress" />
const uuid = require('uuid/v4');

describe('Customer: Test pagination and the corosponding URL parameters', () => {
    // eslint-disable-next-line no-undef
    beforeEach(() => {
        let countryId;
        let paymentMethodId;
        let salesChannelId;
        let groupId;
        let salutationId;

        cy.searchViaAdminApi({
            endpoint: 'country',
            data: {
                field: 'iso',
                type: 'equals',
                value: 'DE',
            },
        }).then(data => {
            countryId = data.id;
            return cy.searchViaAdminApi({
                endpoint: 'payment-method',
                data: {
                    field: 'name',
                    type: 'equals',
                    value: 'Invoice',
                },
            });
        }).then(data => {
            paymentMethodId = data.id;
            return cy.searchViaAdminApi({
                endpoint: 'sales-channel',
                data: {
                    field: 'name',
                    type: 'equals',
                    value: 'Storefront',
                },
            });
        }).then(data => {
            salesChannelId = data.id;
            return cy.searchViaAdminApi({
                endpoint: 'customer-group',
                data: {
                    field: 'name',
                    type: 'equals',
                    value: 'Standard customer group',
                },
            });
        })
            .then(data => {
                groupId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'salutation',
                    data: {
                        field: 'displayName',
                        type: 'equals',
                        value: 'Mr.',
                    },
                });
            })
            .then(data => {
                salutationId = data.id;
                return cy.authenticate();
            })
            .then(auth => {
                let customers = [];

                // eslint-disable-next-line no-plusplus
                for (let i = 1; i <= 26; i++) {
                    const standInId = uuid().replace(/-/g, '');
                    customers.push(
                        {
                            firstName: 'Pep',
                            lastName: `Eroni-${i}`,
                            defaultPaymentMethodId: paymentMethodId,
                            defaultBillingAddressId: standInId,
                            defaultBillingAddress: {
                                id: standInId,
                                firstName: 'Max',
                                lastName: 'Mustermann',
                                street: 'Musterstraße 1',
                                city: 'Schoöppingen',
                                zipcode: '12345',
                                salutationId: salutationId,
                                countryId: countryId,
                            },
                            defaultShippingAddressId: standInId,
                            defaultShippingAddress: {
                                id: standInId,
                                firstName: 'Max',
                                lastName: 'Mustermann',
                                street: 'Musterstraße 1',
                                city: 'Schoöppingen',
                                zipcode: '12345',
                                salutationId: salutationId,
                                countryId: countryId,
                            },
                            customerNumber: uuid().replace(/-/g, ''),
                            email: `test-${i}@example.com`,
                        },
                    );
                }
                customers = customers.map(customer => Object.assign({ countryId, salesChannelId, salutationId, groupId }, customer));
                return cy.request({
                    headers: {
                        Accept: 'application/vnd.api+json',
                        Authorization: `Bearer ${auth.access}`,
                        'Content-Type': 'application/json',
                    },
                    method: 'POST',
                    url: `/${Cypress.env('apiPath')}/_action/sync`,
                    qs: {
                        response: true,
                    },
                    body: {
                        'write-customer': {
                            entity: 'customer',
                            action: 'upsert',
                            payload: customers,
                        },

                    },
                });
            });
    });

    it('@Customer: check that the url parameters get set correctly', { tags: ['pa-customers-orders', 'quarantined'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        const searchTerm = 'Pep';

        // use the search box and check if term gets set (in the function)
        cy.log('typeAndCheckSearchField');
        cy.get('.sw-search-bar__input').typeAndCheckSearchField(searchTerm);
        cy.url().should('contain', `term=${searchTerm}`);

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.get('.sw-search-bar__input').should('have.value', searchTerm);
        cy.url().should('contain', `page=1`);
        cy.url().should('contain', `limit=25`);

        // When search for a term, no sorting is used
        cy.get('.sw-data-grid__cell--4 .icon--regular-chevron-up-xxs').should('not.exist');
        cy.get('.sw-data-grid__cell--4 .icon--regular-chevron-down-xxs').should('not.exist');

        cy.log('change Sorting direction from None to ASC');
        cy.get('.sw-data-grid__cell--4 > .sw-data-grid__cell-content').click('right');

        cy.testListing({
            searchTerm,
            sorting: {
                text: 'Customer number',
                propertyName: 'customerNumber',
                sortDirection: 'ASC',
                location: 4,
            },
            page: 1,
            limit: 25,
        });

        cy.log('change Sorting direction from ASC to DESC');
        cy.get('.sw-data-grid__cell--4 > .sw-data-grid__cell-content').click('right');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.testListing({
            searchTerm,
            sorting: {
                text: 'Customer number',
                propertyName: 'customerNumber',
                sortDirection: 'DESC',
                location: 4,
            },
            page: 1,
            limit: 25,
        });

        cy.log('change items per page to 10');
        cy.get('#perPage').select('10');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.testListing({
            searchTerm,
            sorting: {
                text: 'Customer number',
                propertyName: 'customerNumber',
                sortDirection: 'DESC',
                location: 4,
            },
            page: 1,
            limit: 10,
        });

        cy.log('go to second page');
        cy.get(':nth-child(2) > .sw-pagination__list-button').click();

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.testListing({
            searchTerm,
            sorting: {
                text: 'Customer number',
                propertyName: 'customerNumber',
                sortDirection: 'DESC',
                location: 4,
            },
            page: 2,
            limit: 10,
        });

        cy.log('change sorting to Name');
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content').click('right');

        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.testListing({
            searchTerm,
            sorting: {
                text: 'Name',
                propertyName: 'lastName,firstName',
                sortDirection: 'ASC',
                location: 0,
            },
            page: 2,
            limit: 10,
        });
    });

    it('@Customer: check that the url parameters get applied after a reload', { tags: ['pa-customers-orders'] }, () => {
        cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index?term=Pep&page=2&limit=10&sortBy=lastName,firstName&sortDirection=ASC&naturalSorting=false`);
        cy.get('.sw-skeleton').should('not.exist');
        cy.get('.sw-loader').should('not.exist');

        cy.testListing({
            searchTerm: 'Pep',
            sorting: {
                text: 'Name',
                propertyName: 'lastName,firstName',
                sortDirection: 'ASC',
                location: 0,
            },
            page: 2,
            limit: 10,
        });

        cy.reload();

        cy.testListing({
            searchTerm: 'Pep',
            sorting: {
                text: 'Name',
                propertyName: 'lastName,firstName',
                sortDirection: 'ASC',
                location: 0,
            },
            page: 2,
            limit: 10,
        });
    });
});
