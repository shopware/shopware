/**
 * @sw-package framework
 */

import {
    createWrapper,
    flushPromises,
    records,
    shopwareApplication,
    registerSwMeteorEntityDataTableHooks,
} from './fixtures';

describe('src/app/component/entity/sw-meteor-entity-data-table/routing-and-context', () => {
    registerSwMeteorEntityDataTableHooks();

    it('emits open-detail without routing when detailRoute is not configured', async () => {
        const router = {
            push: jest.fn(),
        };
        const wrapper = createWrapper({
            router,
        });

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__details').trigger('click');

        expect(router.push).not.toHaveBeenCalled();
        expect(wrapper.emitted('open-detail')).toEqual([
            [
                {
                    id: 'record-1',
                    record: records[0],
                },
            ],
        ]);
    });

    it('routes to detailRoute when configured', async () => {
        const router = {
            push: jest.fn(),
        };
        shopwareApplication.view.router = router;
        const wrapper = createWrapper({
            props: {
                detailRoute: 'sw.product.detail',
            },
        });

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__details').trigger('click');

        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.product.detail',
            params: {
                id: 'record-1',
            },
        });
        expect(wrapper.emitted('open-detail')).toEqual([
            [
                {
                    id: 'record-1',
                    record: records[0],
                },
            ],
        ]);
    });

    it('normalizes context-select events from mt-data-table', async () => {
        const wrapper = createWrapper({
            props: {
                additionalContextButtons: [
                    {
                        key: 'set-price',
                        label: 'Set price',
                    },
                ],
            },
        });

        await flushPromises();
        await wrapper.find('.mt-data-table-stub__context-select').trigger('click');

        expect(wrapper.emitted('context-select')).toEqual([
            [
                {
                    key: 'set-price',
                    id: 'record-1',
                    record: records[0],
                },
            ],
        ]);
    });
});
