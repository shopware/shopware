/**
 * @package admin
 */

import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import RepositoryData from 'src/core/data/repository.data';
import IdCollection from 'src/../test/_helper_/id.collection';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

const clientMock = global.repositoryFactoryMock.clientMock;
const responses = global.repositoryFactoryMock.responses;
const repositoryFactory = Shopware.Service('repositoryFactory');
const DEFAULT_CURRENCY = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';

function mockContext() {
    return {
        apiPath: 'http://shopware.local/api',
        apiResourcePath: 'http://shopware.local/api/v2',
        assetsPath: 'http://shopware.local/bundles/',
        basePath: '',
        host: 'shopware.local',
        inheritance: false,
        installationPath: 'http://shopware.local',
        languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
        currencyId: '7924299acc9641bfb8237a06e5aa0fa4',
        liveVersionId: '0fa91ce3e96a4bc2be4bd9ce752c3425',
        pathInfo: '/admin',
        port: 80,
        scheme: 'http',
        schemeAndHttpHost: 'http://shopware.local',
        uri: 'http://shopware.local/admin',
        authToken: {
            access: 'BwP_OL47uNW6k8iQzChh6SxE31XaleO_l4unyLNmFco',
        },
    };
}

function createRepositoryData() {
    return new RepositoryData(undefined, undefined, undefined, undefined, undefined, undefined, undefined, {});
}

describe('repository.data.ts', () => {
    beforeEach(async () => {
        clientMock.resetHistory();
    });

    it('should search with the criteria title', async () => {
        responses.addResponse({
            method: 'POST',
            url: '/search/product',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'POST',
            url: '/search-ids/product',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'POST',
            url: '/search/product?title=ImmaTest',
            status: 200,
            response: {
                data: [],
            },
        });

        responses.addResponse({
            method: 'POST',
            url: '/search-ids/product?title=ImmaTest',
            status: 200,
            response: {
                data: [],
            },
        });

        const repository = repositoryFactory.create('product');

        const criteriaWithoutTitle = new Criteria();
        const criteriaWithTitle = new Criteria();
        criteriaWithTitle.setTitle('ImmaTest');

        repository.search(criteriaWithoutTitle);
        repository.searchIds(criteriaWithoutTitle);

        expect(clientMock.history.post[0].url).toBe('/search/product');
        expect(clientMock.history.post[1].url).toBe('/search-ids/product');

        repository.search(criteriaWithTitle);
        repository.searchIds(criteriaWithTitle);

        expect(clientMock.history.post[2].url).toBe('/search/product?title=ImmaTest');
        expect(clientMock.history.post[3].url).toBe('/search-ids/product?title=ImmaTest');
    });

    it('should build the correct headers', async () => {
        const repositoryData = createRepositoryData('language');
        const actualHeaders = repositoryData.buildHeaders(mockContext());
        const exptectedHeaders = {
            'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            Accept: 'application/vnd.api+json',
            Authorization: 'Bearer BwP_OL47uNW6k8iQzChh6SxE31XaleO_l4unyLNmFco',
            'Content-Type': 'application/json',
            'sw-api-compatibility': true,
            'sw-currency-id': '7924299acc9641bfb8237a06e5aa0fa4',
        };

        expect(actualHeaders).toEqual(exptectedHeaders);
    });

    it('should create one delete operation for multiple deletes', async () => {
        const ids = new IdCollection();

        responses.addResponse({
            method: 'Post',
            url: '_action/sync',
            status: 200,
            response: {},
        });

        const repository = repositoryFactory.create('product', null, {
            useSync: true,
        });
        const context = Shopware.Context.api;
        const product = repository.create(context, ids.get('product'));

        product.name = 'test';
        product.productNumber = ids.get('product');
        product.stock = 10;
        product.price = [
            { currencyId: DEFAULT_CURRENCY, gross: 15, net: 10, linked: false },
        ];
        product.tax = { name: 'test', taxRate: 15 };

        const categories = new EntityCollection(
            product.categories.source,
            product.categories.entity,
            product.categories.context,
            product.categories.criteria,
        );

        let factory = repositoryFactory.create('category');
        categories.add(factory.create(context, ids.get('cat-1')));
        categories.add(factory.create(context, ids.get('cat-2')));
        categories.add(factory.create(context, ids.get('cat-3')));

        const properties = new EntityCollection(
            product.properties.source,
            product.properties.entity,
            product.properties.context,
            product.properties.criteria,
        );

        factory = repositoryFactory.create('property_group_option');
        properties.add(factory.create(context, ids.get('option-1')));
        properties.add(factory.create(context, ids.get('option-2')));
        properties.add(factory.create(context, ids.get('option-3')));

        product.getOrigin().properties = properties;
        product.getOrigin().categories = categories;

        const changesetGenerator = new ChangesetGenerator();
        const changes = changesetGenerator.generate(product);

        expect(changes.deletionQueue).toHaveLength(6);

        // send new product to the server
        await repository.save(product);

        // expect that one request get send
        expect(clientMock.history.post).toHaveLength(1);

        // check that request for the product creation was created correctly
        const request = clientMock.history.post[0];

        expect(request.url).toBe('_action/sync');
        expect(request.headers['single-operation']).toBe(true);

        expect(request.data).toEqual(
            JSON.stringify([
                {
                    action: 'delete',
                    payload: [
                        {
                            productId: ids.get('product'),
                            optionId: ids.get('option-1'),
                        },
                        {
                            productId: ids.get('product'),
                            optionId: ids.get('option-2'),
                        },
                        {
                            productId: ids.get('product'),
                            optionId: ids.get('option-3'),
                        },
                    ],
                    entity: 'product_property',
                },
                {
                    action: 'delete',
                    payload: [
                        {
                            productId: ids.get('product'),
                            categoryId: ids.get('cat-1'),
                        },
                        {
                            productId: ids.get('product'),
                            categoryId: ids.get('cat-2'),
                        },
                        {
                            productId: ids.get('product'),
                            categoryId: ids.get('cat-3'),
                        },
                    ],
                    entity: 'product_category',
                },
                {
                    key: 'write',
                    action: 'upsert',
                    entity: 'product',
                    payload: [
                        {
                            id: ids.get('product'),
                            price: [
                                {
                                    currencyId: DEFAULT_CURRENCY,
                                    gross: 15,
                                    net: 10,
                                    linked: false,
                                },
                            ],
                            productNumber: ids.get('product'),
                            stock: 10,
                            name: 'test',
                        },
                    ],
                },
            ]),
        );
    });

    it('should throw an 400 error when httpClient post call fails with error without source property', async () => {
        const productRepository = repositoryFactory.create('product');
        const product = productRepository.create();
        product.name = 'Our amazing product';

        responses.filterResponses((response) => {
            return response.url !== '_action/sync';
        });

        responses.addResponse({
            method: 'POST',
            url: '_action/sync',
            status: 400,
            response: {
                errors: [
                    {
                        status: '400',
                        code: 'CONTENT__DUPLICATE_PRODUCT_NUMBER',
                        title: 'Bad Request',
                        detail: 'Product with number "SW10000" already exists.',
                        meta: {
                            parameters: {
                                number: 'SW10000',
                            },
                        },
                    },
                ],
            },
        });

        let thrownError;

        try {
            await productRepository.saveWithSync(product);
        } catch (e) {
            thrownError = e;
        }

        expect(thrownError.message).toBe('Request failed with status code 400');
    });
});
