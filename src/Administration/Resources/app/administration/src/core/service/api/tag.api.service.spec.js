import TagApiService from 'src/core/service/api/tag.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function getTagApiService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const tagApiService = new TagApiService(client, loginService);
    return { tagApiService, clientMock };
}

describe('tagApiService', () => {
    it('is registered correctly', async () => {
        const { tagApiService } = getTagApiService();
        expect(tagApiService).toBeInstanceOf(TagApiService);
    });

    it('is send filterIds request correctly', async () => {
        const { tagApiService, clientMock } = getTagApiService();
        let didRequest = false;

        clientMock.onPost('/_admin/tag-filter-ids')
            .reply(() => {
                didRequest = true;

                return [200, {}];
            });

        tagApiService.filterIds({});

        expect(didRequest).toBeTruthy();
    });

    it('is handling merge correctly', async () => {
        const { tagApiService } = getTagApiService();
        const bulkMergeProgress = {
            isRunning: false,
            currentAssignment: null,
            progress: 0,
            total: 0
        };
        const firstProductIdsBatch = Array.from(Array(200).keys());
        const tagRepositoryMock = {
            create: jest.fn(() => {
                return { id: 't4' };
            }),
            save: jest.fn((tag) => {
                expect(tag.id).toEqual('t4');

                if (bulkMergeProgress.currentAssignment === null) {
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(tag.name).toEqual('foo');
                    return;
                }

                if (bulkMergeProgress.currentAssignment === 'products' && bulkMergeProgress.progress === 0) {
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(tag.products).toEqual(firstProductIdsBatch.map((id) => { return { id }; }));
                    return;
                }

                if (bulkMergeProgress.currentAssignment === 'products') {
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(tag.products).toEqual([200, 201, 202].map((id) => { return { id }; }));
                    return;
                }

                expect(tag[bulkMergeProgress.currentAssignment]).toEqual([0, 1, 2].map((id) => { return { id }; }));
            }),
            syncDeleted: jest.fn((ids) => {
                expect(ids).toEqual(['t1', 't2', 't3']);
            })
        };
        const generalRepositoryMock = {
            searchIds: jest.fn((criteria) => {
                const { type, field, value } = criteria.filters[0];
                expect(type).toEqual('equalsAny');
                expect(field).toEqual('tags.id');
                expect(value).toEqual('t1|t2|t3');
                expect(criteria.limit).toEqual(200);

                if (bulkMergeProgress.currentAssignment === 'products' && criteria.page === 1) {
                    return { data: firstProductIdsBatch, total: 203 };
                }

                if (bulkMergeProgress.currentAssignment === 'products') {
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(criteria.page).toEqual(2);
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(bulkMergeProgress.progress).toEqual(200);
                    // eslint-disable-next-line jest/no-conditional-expect
                    expect(bulkMergeProgress.total).toEqual(203);

                    return { data: [200, 201, 202], total: 203 };
                }

                expect(criteria.page).toEqual(1);

                return { data: [0, 1, 2], total: 3 };
            })
        };

        tagApiService.getRepository = (entity) => {
            if (entity === 'tag') {
                return tagRepositoryMock;
            }

            return generalRepositoryMock;
        };

        await tagApiService.merge(
            ['t1', 't2', 't3'],
            'foo',
            {
                name: {},
                products: {
                    relation: 'many_to_many',
                    entity: 'product'
                },
                categories: {
                    relation: 'many_to_many',
                    entity: 'category'
                },
                rules: {
                    relation: 'many_to_many',
                    entity: 'rule'
                }
            },
            bulkMergeProgress
        );

        expect(bulkMergeProgress.isRunning).toBeTruthy();
        expect(tagRepositoryMock.create).toHaveBeenCalledTimes(1);
        expect(generalRepositoryMock.searchIds).toHaveBeenCalledTimes(4);
        expect(tagRepositoryMock.save).toHaveBeenCalledTimes(5);
        expect(tagRepositoryMock.syncDeleted).toHaveBeenCalledTimes(1);
    });
});
