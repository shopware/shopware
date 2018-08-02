import jsonApiParserService from 'src/core/service/jsonapi-parser.service';
import { Application } from 'src/core/shopware';
import EntityProxy from 'src/core/data/EntityProxy';
import CriteriaFactory from 'src/core/factory/criteria.factory';

import { itAsync } from '../../../async-helper';

describe('core/service/jsonapi-parser.service.js', () => {
    it('should reject when we are providing an array, number, undefined or null', () => {
        const arrayParser = jsonApiParserService([1, 2, 3]);
        expect(arrayParser).is.equal(null);

        const nestedArrayParser = jsonApiParserService([
            { id: 42, name: 'foo' },
            { id: 92, name: 'bar' }
        ]);
        expect(nestedArrayParser).is.equal(null);

        const numberParser = jsonApiParserService(42);
        expect(numberParser).is.equal(null);

        const negativeNumberParser = jsonApiParserService(-3);
        expect(negativeNumberParser).is.equal(null);

        const undefinedParser = jsonApiParserService(undefined);
        expect(undefinedParser).is.equal(null);

        const nullParser = jsonApiParserService(null);
        expect(nullParser).is.equal(null);
    });

    it('should not parse a malformed JSON string', () => {
        const brokenJsonParser = jsonApiParserService('{foo:"bar"}');
        expect(brokenJsonParser).is.equal(null);
    });

    it('should parse a valid JSON string which is not following the spec', () => {
        const validJsonParser = jsonApiParserService('{"foo":"bar"}');
        expect(validJsonParser).is.deep.equal({
            foo: 'bar'
        });
    });

    it('should parse a valid JSON string which follows the spec', () => {
        const validJsonApiParser = jsonApiParserService(JSON.stringify({
            data: [{
                id: 1,
                type: 'article',
                attributes: {
                    title: 'Foo bar'
                },
                relationships: {
                    author: {
                        data: { id: 1, type: 'people' }
                    }
                }
            }],
            included: [{
                type: 'people',
                id: 1,
                attributes: {
                    name: 'Peter'
                }
            }]
        }));

        expect(JSON.stringify(validJsonApiParser)).is.equal(JSON.stringify({
            links: null,
            errors: null,
            data: [{
                id: 1,
                type: 'article',
                links: {},
                meta: {},
                title: 'Foo bar',
                author: {
                    id: 1,
                    type: 'people',
                    links: {},
                    meta: {},
                    name: 'Peter'
                }
            }],
            associations: {},
            aggregations: null,
            parsed: true
        }));
    });

    itAsync('should ensure the right object structure got returned from the api using a search call', (done) => {
        const serviceContainer = Application.getContainer('service');
        const productService = serviceContainer.productService;

        const headers = productService.getBasicHeaders();
        const params = {
            term: 'Awesome'
        };

        productService.httpClient.post(`${productService.getApiBasePath(null, 'search')}`, params, { headers })
            .then((response) => {
                const data = response.data;

                expect(data.aggregations).to.be.an('array');
                expect(data.data).to.be.an('array');
                expect(data.included).to.be.an('array');

                expect(data.links).to.be.an('object');
                expect(data.links.first).to.be.a('string');
                expect(data.links.last).to.be.a('string');
                expect(data.links.self).to.be.a('string');

                expect(data.meta).to.be.an('object');
                expect(data.meta.fetchCount).to.be.a('number');
                expect(data.meta.total).to.be.a('number');
                done();
            })
            .catch((err) => {
                done(err);
            });
    });

    itAsync('should ensure the right object structure got returned from the api using an aggregation call', (done) => {
        // generate catalog proxy and save it on the server
        const serviceContainer = Application.getContainer('service');
        const catalogService = serviceContainer.catalogService;

        const catalogEntity = new EntityProxy('catalog', 'catalogService');
        const catalogId = catalogEntity.id;
        catalogEntity.name = 'KarmaUnitCatalog';

        const headers = catalogService.getBasicHeaders();
        const params = {
            offset: 0,
            limit: 1,
            aggregations: {
                productCount: {
                    count: { field: 'catalog.products.id' }
                },
                categoryCount: {
                    count: { field: 'catalog.categories.id' }
                },
                mediaCount: {
                    count: { field: 'catalog.media.id' }
                }
            },
            filter: [
                CriteriaFactory.term('id', catalogId).getQuery()
            ]
        };

        catalogEntity.save().then(() => {
            catalogService.httpClient.post(`${catalogService.getApiBasePath(null, 'search')}`, params, { headers })
                .then((response) => {
                    const data = response.data;

                    expect(data.aggregations).to.be.an('object');

                    expect(data.aggregations).to.deep.include({
                        productCount: { count: '0' },
                        categoryCount: { count: '0' },
                        mediaCount: { count: '0' }
                    });

                    expect(data.data.length).to.be.equal(1);

                    expect(data.links).to.be.an('object');
                    expect(data.links.first).to.be.a('string');
                    expect(data.links.last).to.be.a('string');
                    expect(data.links.self).to.be.a('string');

                    expect(data.meta.fetchCount).to.be.a('number');
                    expect(data.meta.total).to.be.a('number');

                    expect(data.meta.fetchCount).to.be.equal(1);
                    expect(data.meta.total).to.be.equal(1);
                    done();
                })
                .catch((err) => {
                    done(err);
                });
        }).catch((err) => {
            done(err);
        });
    });
});
