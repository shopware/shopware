/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { defineComponent, ref } from 'vue';
import type { Ref } from 'vue';
import Entity from 'src/core/data/entity.data';
import { getPublishedDataSets } from 'src/core/service/extension-api-data.service';
import usePublishedData from './use-published-data';

function mountWithPublishedData<T>(id: string, source: Ref<T>): { unmount: () => void } {
    return mount(
        defineComponent({
            template: '<div />',
            setup() {
                usePublishedData(id, source);
            },
        }),
    ) as unknown as { unmount: () => void };
}

/** Fakes the one message an app sends to write into a published data set. */
function appWritesDataSet(id: string, data: unknown): void {
    jest.spyOn(window, 'addEventListener').mockImplementationOnce((event, handler) => {
        (handler as (event: { data: string }) => void)({
            data: JSON.stringify({
                _type: 'datasetUpdate',
                _data: { id, data },
                _callbackId: Shopware.Utils.createId(),
            }),
        });
    });
}

describe('src/app/composables/use-published-data', () => {
    it('publishes the ref under its id', async () => {
        mountWithPublishedData('use-published-data__scalar', ref('published'));

        await flushPromises();

        expect(getPublishedDataSets().find((set) => set.id === 'use-published-data__scalar')?.data).toBe('published');
    });

    it('republishes the ref when it changes', async () => {
        const source = ref('before');

        mountWithPublishedData('use-published-data__changing', source);
        await flushPromises();

        source.value = 'after';
        await flushPromises();

        expect(getPublishedDataSets().find((set) => set.id === 'use-published-data__changing')?.data).toBe('after');
    });

    it('writes an app update back into the ref', async () => {
        const source = ref('before');

        appWritesDataSet('use-published-data__inbound', 'from the app');
        mountWithPublishedData('use-published-data__inbound', source);

        await flushPromises();

        expect(source.value).toBe('from the app');
    });

    it('writes an app update back into a property of a published entity', async () => {
        const entity = new Entity(Shopware.Utils.createId(), 'jest', { name: 'before' });
        const source = ref(entity);

        appWritesDataSet('use-published-data__entity', { name: 'after' });
        mountWithPublishedData('use-published-data__entity', source);

        await flushPromises();

        expect(source.value.name).toBe('after');
        // The write must not replace the entity, or its draft handling goes with it.
        expect(typeof source.value.getDraft).toBe('function');
    });

    it('unpublishes the data set when the component unmounts', async () => {
        const wrapper = mountWithPublishedData('use-published-data__unmount', ref('published'));

        await flushPromises();
        expect(getPublishedDataSets().some((set) => set.id === 'use-published-data__unmount')).toBe(true);

        wrapper.unmount();
        await flushPromises();

        expect(getPublishedDataSets().some((set) => set.id === 'use-published-data__unmount')).toBe(false);
    });

    it('warns and publishes nothing when called outside setup', () => {
        const warn = jest.spyOn(Shopware.Utils.debug, 'warn').mockImplementation(() => {});

        usePublishedData('use-published-data__no-setup', ref('published'));

        expect(warn).toHaveBeenCalledWith('usePublishedData', expect.stringContaining('during setup'));
        expect(getPublishedDataSets().some((set) => set.id === 'use-published-data__no-setup')).toBe(false);

        warn.mockRestore();
    });
});
