import createId from 'uuid/v4';

const productId = createId().replace(/-/g, '');
const mediaId = createId().replace(/-/g, '');

describe('Test order history with downloadable products', () => {
    beforeEach(() => {
        cy.createCustomerFixtureStorefront()
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'media-default-folder',
                    data: {
                        field: 'entity',
                        value: 'product_download',
                    },
                });
            })
            .then((defaultFolder) => {
                return cy.createProductFixture({
                    id: productId,
                    name: 'Digital product',
                    productNumber: 'RS-11111',
                    maxPurchase: 1,
                    downloads: [
                        {
                            media: {
                                id: mediaId,
                                private: true,
                                mediaFolderId: defaultFolder.relationships.folder.data.id,
                            },
                        },
                    ],
                });
            })
            .then(() => {
                return cy.authenticate().then((result) => {
                    const formData = new FormData();
                    const file = new File(['test'], 'test.txt');
                    formData.append('file', file, 'test.txt');

                    return cy.request({
                        headers: {
                            Accept: 'application/vnd.api+json',
                            Authorization: `Bearer ${result.access}`,
                            'Content-Type': 'multipart/form-data',
                        },
                        method: 'POST',
                        url: `${Cypress.env('apiPath')}/_action/media/${mediaId}/upload?extension=txt&fileName=test`,
                        qs: {
                            response: true,
                        },
                        formData,
                    });
                });
            })
            .then(() => {
                return cy.createOrder(productId, {
                    username: 'test@example.com',
                    password: 'shopware',
                });
            })
            .then(() => {
                return cy.searchViaAdminApi({
                    endpoint: 'order-transaction',
                    data: {
                        field: 'order.orderNumber',
                        value: '10000',
                    },
                });
            })
            .then((result) => {
                return cy.requestAdminApi(
                    'POST',
                    `${Cypress.env('apiPath')}/_action/order_transaction/${result.id}/state/paid`,
                );
            })
            .then(() => {
                cy.visit('/account/login');

                // Login
                cy.get('.login-card').should('be.visible');
                cy.get('#loginMail').typeAndCheckStorefront('test@example.com');
                cy.get('#loginPassword').typeAndCheckStorefront('shopware');
                cy.get('.login-submit [type="submit"]').click();

                cy.visit('/account/order');
            });
    });

    it('@base @checkout: should show downloads in account order history', { tags: ['pa-checkout'] }, () => {
        cy.intercept({
            url: '/account/order/download/*/*',
            method: 'GET',
        }).as('downloadRequest');

        // Order detail is expandable
        cy.get('.order-table').should('be.visible');
        cy.get('.order-table:nth-of-type(1) .order-table-header-order-number').contains('Order number: 10000');
        cy.get('.order-table:nth-of-type(1) .order-hide-btn').click();
        cy.get('.order-detail-content').should('be.visible');
        cy.get('.download-item.download-item-file-name').should('be.visible');
        cy.contains('.download-item.download-item-file-name', 'test.txt');
        cy.get('.download-item.download-item-download-file').should('be.visible');

        cy.get('.download-item-view-file-text-btn').click();

        cy.wait('@downloadRequest').its('response.statusCode').should('equal', 200);
    });
});
