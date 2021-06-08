// / <reference types="Cypress" />
const uuid = require('uuid/v4');

describe('Customer: Test pagination and the corosponding URL parameters', () => {
    // eslint-disable-next-line no-undef
    before(() => {
        let countryId, paymentMethodId, salesChannelId, groupId, salutationId;
        cy.setToInitialState().then(() => {
            cy.searchViaAdminApi({
                endpoint: 'country', data: {
                    field: 'iso',
                    type: 'equals',
                    value: 'DE'
                }
            }).then(data => {
                countryId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'payment-method',
                    data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Invoice'
                    }
                })
            }).then(data => {
                paymentMethodId = data.id
                return cy.searchViaAdminApi({
                    endpoint: 'sales-channel', data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Storefront'
                    }
                })
            }).then(data => {
                salesChannelId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'customer-group', data: {
                        field: 'name',
                        type: 'equals',
                        value: 'Standard customer group'
                    }
                })
            }).then(data => {
                groupId = data.id;
                return cy.searchViaAdminApi({
                    endpoint: 'salutation', data: {
                        field: 'displayName',
                        type: 'equals',
                        value: 'Mr.'
                    }
                })
            }).then(data => {
                salutationId = data.id;
                return cy.authenticate()
            }).then(auth => {

                let customers = [];
                for (let i = 1; i <= 26; i++) {
                    const standInId = uuid().replace(/-/g, '');
                    customers.push(
                        {
                            firstName: 'Pep',
                            lastName: `Eroni-${i}`,
                            defaultPaymentMethodId: paymentMethodId,
                            defaultBillingAddressId: standInId,
                            defaultShippingAddressId: standInId,
                            customerNumber: uuid().replace(/-/g, ''),
                            email: `test-${i}@example.com`
                        }
                    );
                }
                customers = customers.map(customer => Object.assign({ countryId, salesChannelId, salutationId, groupId }, customer));
                return cy.request({
                    headers: {
                        Accept: 'application/vnd.api+json',
                        Authorization: `Bearer ${auth.access}`,
                        'Content-Type': 'application/json'
                    },
                    method: 'POST',
                    url: '/api/_action/sync',
                    qs: {
                        response: true
                    },
                    body: {
                        'write-customer': {
                            'entity': 'customer',
                            'action': 'upsert',
                            'payload': customers
                        }

                    }
                })
            });
        });

    })

    it('@Customer: check that the url parameters get set', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index`);

        // use the search box and check if term gets set (in the function)
        cy.get('.sw-search-bar__input').typeAndCheckSearchField('Pep');

        cy.testListing({
            searchTerm: 'Pep',
            sorting: {
                text: 'Customer number',
                propertyName: 'customerNumber',
                sortDirection: 'DESC',
                location: 4
            },
            page: 1,
            limit: 25
        });

        cy.log('change Sorting direction from DESC to ASC')
        cy.get('.sw-data-grid__cell--4 > .sw-data-grid__cell-content').click('right');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            searchTerm: 'Pep',
            sorting: {
                text: 'Customer number',
                propertyName: 'customerNumber',
                sortDirection: 'ASC',
                location: 4
            },
            page: 1,
            limit: 25
        });

        cy.log('change items per page to 10');
        cy.get('#perPage').select("10");
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            searchTerm: 'Pep',
            sorting: {
                text: 'Customer number',
                propertyName: 'customerNumber',
                sortDirection: 'ASC',
                location: 4
            },
            page: 1,
            limit: 10
        });

        cy.log('go to second page')
        cy.get(':nth-child(2) > .sw-pagination__list-button').click();
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            searchTerm: 'Pep',
            sorting: {
                text: 'Customer number',
                propertyName: 'customerNumber',
                sortDirection: 'ASC',
                location: 4
            },
            page: 2,
            limit: 10
        });

        cy.log('change sorting to Name')
        cy.get('.sw-data-grid__cell--0 > .sw-data-grid__cell-content').click('right');
        cy.get('.sw-data-grid-skeleton').should('not.exist');

        cy.testListing({
            searchTerm: 'Pep',
            sorting: {
                text: 'Name',
                propertyName: 'lastName,firstName',
                sortDirection: 'ASC',
                location: 0
            },
            page: 2,
            limit: 10
        });
    });

    it('@Customer: check that the url parameters get applied after a reload', () => {
        cy.loginViaApi();

        cy.openInitialPage(`${Cypress.env('admin')}#/sw/customer/index?term=Pep&page=2&limit=10&sortBy=lastName,firstName&sortDirection=ASC&naturalSorting=false`)

        cy.testListing({
            searchTerm: 'Pep',
            sorting: {
                text: 'Name',
                propertyName: 'lastName,firstName',
                sortDirection: 'ASC',
                location: 0
            },
            page: 2,
            limit: 10
        });

        cy.reload();

        cy.testListing({
            searchTerm: 'Pep',
            sorting: {
                text: 'Name',
                propertyName: 'lastName,firstName',
                sortDirection: 'ASC',
                location: 0
            },
            page: 2,
            limit: 10
        });
    });
});
