/**
 * @sw-package inventory
 */
import { mount, flushPromises } from '@vue/test-utils';

const uneceCodes = [
    { code: 'H87', name: 'piece' },
    { code: 'KGM', name: 'kilogram' },
];

async function createWrapper({
    privileges = ['scale_unit.editor'],
    getUneceCodes = () => Promise.resolve(uneceCodes),
    snippets = {},
}: {
    privileges?: string[];
    getUneceCodes?: () => Promise<typeof uneceCodes>;
    snippets?: Record<string, string>;
} = {}) {
    const wrapper = mount(await wrapTestComponent('sw-settings-units-detail', { sync: true }), {
        props: {
            unitId: 'unit-id',
        },
        global: {
            mocks: {
                $te: (key: string) => key in snippets,
                $t: (key: string) => snippets[key] ?? key,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        get: () =>
                            Promise.resolve({
                                id: 'unit-id',
                                name: 'Kilogram',
                                shortCode: 'kg',
                                uneceCode: 'KGM',
                                isNew: () => false,
                            }),
                    }),
                },
                acl: {
                    can: (identifier: string) => privileges.includes(identifier),
                },
                customFieldDataProviderService: {
                    getCustomFieldSets: () => Promise.resolve([]),
                },
                unitUneceCodeService: {
                    getUneceCodes: jest.fn(getUneceCodes),
                },
            },
            stubs: {
                'sw-page': {
                    template:
                        '<div class="sw-page"><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot></slot></div>',
                },
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                },
                'sw-single-select': {
                    props: [
                        'value',
                        'options',
                        'isLoading',
                        'disabled',
                        'error',
                    ],
                    template:
                        '<div class="sw-single-select" :data-value="value" :data-loading="isLoading" :data-disabled="disabled"></div>',
                },
                'sw-button-process': true,
                'sw-language-switch': true,
                'mt-text-field': true,
                'sw-custom-field-set-renderer': true,
                'sw-skeleton': true,
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('module/sw-settings-units/page/sw-settings-units-detail', () => {
    it('should offer the unit codes as code and name', async () => {
        const wrapper = await createWrapper();
        const select = wrapper.find('.sw-settings-units__unece-code');

        expect(select.attributes('data-value')).toBe('KGM');
        expect(select.attributes('data-loading')).toBe('false');
        expect(wrapper.vm.uneceCodeOptions).toEqual([
            { value: 'H87', label: 'H87 - piece' },
            { value: 'KGM', label: 'KGM - kilogram' },
        ]);
    });

    it('should use the translated name of a common unit code', async () => {
        const wrapper = await createWrapper({
            snippets: { 'sw-settings-units.uneceCodes.KGM': 'Kilogramm' },
        });

        expect(wrapper.vm.uneceCodeOptions).toEqual([
            { value: 'H87', label: 'H87 - piece' },
            { value: 'KGM', label: 'KGM - Kilogramm' },
        ]);
    });

    it('should show an error when the unit codes cannot be loaded', async () => {
        const createNotification = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        const wrapper = await createWrapper({ getUneceCodes: () => Promise.reject(new Error('failed')) });

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-settings-units.notification.errorLoadingUneceCodes',
            }),
        );
        expect(wrapper.vm.uneceCodeOptions).toEqual([]);
        expect(wrapper.find('.sw-settings-units__unece-code').attributes('data-loading')).toBe('false');

        createNotification.mockRestore();
    });

    it('should disable the unit code without edit permission', async () => {
        const wrapper = await createWrapper({ privileges: [] });

        expect(wrapper.find('.sw-settings-units__unece-code').attributes('data-disabled')).toBe('true');
    });
});
