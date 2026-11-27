/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

const getMock = jest.fn((id) =>
    Promise.resolve({
        id,
        name: `name-${id}`,
        config: { translated: true, label: {} },
        relations: [],
        discardChanges: jest.fn(),
    }),
);
const saveMock = jest.fn(() => Promise.resolve());

async function createWrapper(route = reactive({ params: { id: 'set-a' } })) {
    return mount(await wrapTestComponent('sw-settings-custom-field-set-detail', { sync: true }), {
        global: {
            mocks: {
                $route: route,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: getMock,
                        save: saveMock,
                    }),
                },
            },
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions" /><slot name="content" /></div>',
                },
                'sw-custom-field-set-detail-base': {
                    props: ['set'],
                    template: '<div class="sw-custom-field-set-detail-base" />',
                },
                'sw-custom-field-list': {
                    props: ['set'],
                    template: '<div class="sw-custom-field-list" />',
                },
            },
        },
    });
}

describe('src/module/sw-settings-custom-field/page/sw-settings-custom-field-set-detail', () => {
    beforeEach(() => {
        getMock.mockClear();
        saveMock.mockClear();
    });

    it('should load the new set when the route id changes', async () => {
        const route = reactive({ params: { id: 'set-a' } });
        const wrapper = await createWrapper(route);
        await flushPromises();

        expect(getMock).toHaveBeenLastCalledWith('set-a', expect.anything(), expect.anything());
        expect(wrapper.findComponent('.sw-custom-field-list').props('set').id).toBe('set-a');

        route.params.id = 'set-b';
        await flushPromises();

        expect(getMock).toHaveBeenLastCalledWith('set-b', expect.anything(), expect.anything());
        expect(wrapper.findComponent('.sw-custom-field-set-detail-base').props('set').name).toBe('name-set-b');
        expect(wrapper.findComponent('.sw-custom-field-list').props('set').id).toBe('set-b');
    });

    it('should load the set only once when saving', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        getMock.mockClear();

        await wrapper.find('.sw-settings-set-detail__save-action').trigger('click');
        await flushPromises();

        expect(saveMock).toHaveBeenCalledTimes(1);
        expect(getMock).toHaveBeenCalledTimes(1);
    });
});
