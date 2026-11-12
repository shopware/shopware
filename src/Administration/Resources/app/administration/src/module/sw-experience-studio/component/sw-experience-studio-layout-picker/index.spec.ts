import { shallowMount } from '@vue/test-utils';
import type { ComponentPublicInstance } from 'vue';

async function createWrapper(total = 2, search = jest.fn()) {
    const [picker, grid] = await Promise.all([
        wrapTestComponent('sw-experience-studio-layout-picker', { sync: true }),
        wrapTestComponent('sw-data-grid', { sync: true }),
    ]);
    search.mockResolvedValue(
        Object.assign(
            [
                { id: 'layout-a', name: 'Home', version: 1, updatedAt: '2026-11-09T10:00:00Z' },
                { id: 'layout-b', name: 'Summer collection', version: 2, updatedAt: '2026-11-08T10:00:00Z' },
            ],
            { total },
        ),
    );

    return shallowMount(picker, {
        props: { openLayoutIds: ['layout-a'] },
        global: {
            provide: {
                repositoryFactory: { create: () => ({ search }) },
                acl: { can: () => false },
            },
            stubs: {
                'sw-modal': { template: '<div><slot /><footer><slot name="modal-footer" /></footer></div>' },
                'sw-data-grid': grid,
                'sw-provide': { inheritAttrs: false, template: '<slot />' },
                'sw-loader': true,
                'sw-pagination': true,
            },
        },
    });
}

describe('layout picker', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it('lists layouts without a checkbox selection column or an open confirmation button', () => {
        const grid = wrapper.get('.sw-experience-studio-layout-picker__grid');

        expect(grid.findAll('.sw-data-grid__body .sw-data-grid__row')).toHaveLength(2);
        expect(grid.find('.sw-data-grid__cell--selection').exists()).toBe(false);
        expect(grid.find('input[type="checkbox"]').exists()).toBe(false);
        expect(wrapper.get('footer').findAll('button')).toHaveLength(1);
        expect(wrapper.get('footer button').text()).toBe('global.default.cancel');
    });

    it.each([
        { id: 'layout-a', name: 'Home', action: 'activate' },
        { id: 'layout-b', name: 'Summer collection', action: 'open' },
    ])('opens $name directly from its name, including layouts already open', async ({ id, name, action }) => {
        const button = wrapper.get(`button[title="sw-experience-studio.workspace.layoutPicker.${action}"]`);

        expect(button.text()).toBe(name);
        await button.trigger('click');

        expect(wrapper.emitted('select')).toEqual([[id]]);
    });

    it('still closes the picker when cancelling', async () => {
        await wrapper.get('footer button').trigger('click');

        expect(wrapper.emitted('close')).toEqual([[]]);
        expect(wrapper.emitted('select')).toBeUndefined();
    });

    it('loads page two from the pagination payload and displays the returned layouts', async () => {
        wrapper.unmount();
        const search = jest.fn();
        wrapper = await createWrapper(30, search);
        await flushPromises();
        search.mockResolvedValueOnce(
            Object.assign(
                [
                    { id: 'older-layout', name: 'Older layout', version: 1 },
                ],
                { total: 30 },
            ),
        );
        const pagination = wrapper.getComponent<ComponentPublicInstance>('sw-pagination-stub');

        pagination.vm.$emit('page-change', { page: 2, limit: 25 });
        await flushPromises();

        expect(search).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2, limit: 25 }), Shopware.Context.api);
        expect(pagination.attributes('page')).toBe('2');
        expect(wrapper.get('.sw-experience-studio-layout-picker__grid').text()).toContain('Older layout');
        expect(wrapper.find('.mt-banner').exists()).toBe(false);
    });

    it('returns to page one on search and keeps the search term when paginating', async () => {
        wrapper.unmount();
        const search = jest.fn();
        wrapper = await createWrapper(30, search);
        await flushPromises();
        const pagination = wrapper.getComponent<ComponentPublicInstance>('sw-pagination-stub');
        pagination.vm.$emit('page-change', { page: 2, limit: 25 });
        await flushPromises();
        wrapper.getComponent<ComponentPublicInstance>({ name: 'mt-text-field' }).vm.$emit('update:modelValue', '  Summer  ');
        await flushPromises();
        expect(search).toHaveBeenLastCalledWith(
            expect.objectContaining({ page: 1, limit: 25, term: 'Summer' }),
            Shopware.Context.api,
        );

        pagination.vm.$emit('page-change', { page: 2, limit: 25 });
        await flushPromises();
        expect(search).toHaveBeenLastCalledWith(
            expect.objectContaining({ page: 2, limit: 25, term: 'Summer' }),
            Shopware.Context.api,
        );
    });
});
