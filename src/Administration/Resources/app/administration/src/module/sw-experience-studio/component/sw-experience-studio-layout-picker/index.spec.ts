import { shallowMount } from '@vue/test-utils';

async function createWrapper() {
    const [picker, grid] = await Promise.all([
        wrapTestComponent('sw-experience-studio-layout-picker', { sync: true }),
        wrapTestComponent('sw-data-grid', { sync: true }),
    ]);
    const search = jest.fn().mockResolvedValue(
        Object.assign(
            [
                { id: 'layout-a', name: 'Home', version: 1, updatedAt: '2026-11-09T10:00:00Z' },
                { id: 'layout-b', name: 'Summer collection', version: 2, updatedAt: '2026-11-08T10:00:00Z' },
            ],
            { total: 2 },
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
});
