/**
 * @package services-settings
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

const repositoryMockFactory = () => {
    return {
        get: () => Promise.resolve({}),
        search: (criteria) => {
            const profiles = [
                {
                    label: 'Default product',
                    sourceEntity: 'product',
                    config: [],
                },
                {
                    label: 'Default configurator settings',
                    sourceEntity: 'product_configurator_setting',
                    config: [],
                },
                {
                    label: 'Default category',
                    sourceEntity: 'category',
                    config: [],
                },
                {
                    label: 'Default media',
                    sourceEntity: 'media',
                    config: [],
                },
            ];

            return Promise.resolve(profiles.filter((profile) => {
                let isAllowed = true;

                criteria.filters.forEach(filter => {
                    if (filter.type === 'equals' && profile[filter.field] !== filter.value) {
                        isAllowed = false;
                    }
                });

                return isAllowed;
            }));
        },
    };
};

describe('components/sw-import-export-exporter', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = mount(await wrapTestComponent('sw-import-export-exporter', { sync: true }), {
            global: {
                stubs: {
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-loader': true,
                    'sw-icon': true,
                    'sw-switch-field': true,
                    'sw-field-error': true,
                    'sw-import-export-progress': true,
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                    'sw-alert': await wrapTestComponent('sw-alert'),
                    'sw-import-export-exporter': await wrapTestComponent('sw-import-export-exporter', { sync: true }),
                    'sw-button': true,
                    'sw-product-variant-info': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                    'sw-alert-deprecated': {
                        template: '<div><slot></slot></div>',
                    },
                },
                provide: {
                    shortcutService: {
                        startEventListener: () => {},
                        stopEventListener: () => {},
                    },
                    importExport: {
                        export: (profileId, cb, config) => {
                            if (!config.error) {
                                return Promise.resolve();
                            }

                            // eslint-disable-next-line prefer-promise-reject-errors
                            return Promise.reject({
                                response: {
                                    data: {
                                        errors: [
                                            {
                                                code: 'This is an error code',
                                                detail: 'This is an detailed error message',
                                            },
                                        ],
                                    },
                                },
                            });
                        },
                    },
                    repositoryFactory: {
                        create: () => repositoryMockFactory(),
                    },
                },
            },
        });

        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should not show the warning when nothing is selected', async () => {
        expect(wrapper.find('.sw-import-export-exporter__variants-warning').exists()).toBeFalsy();
    });

    it('should not show the warning when a product profile without variants is selected', async () => {
        await wrapper.find('.sw-import-export-exporter__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        const defaultProduct = await wrapper.find('.sw-select-option--0 .sw-highlight-text');
        expect(defaultProduct.text()).toBe('Default product');

        await defaultProduct.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('Default product');
        expect(wrapper.find('.sw-import-export-exporter__variants-warning').exists()).toBeFalsy();
    });

    it('should not show the warning when a product profile should not export variants', async () => {
        await wrapper.find('.sw-import-export-exporter__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        const defaultProduct = await wrapper.find('.sw-select-option--0 .sw-select-result__result-item-text');
        expect(defaultProduct.text()).toBe('Default product');

        await defaultProduct.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('Default product');

        const variantsWarning = wrapper.find('.sw-import-export-exporter__variants-warning');

        expect(variantsWarning.exists()).toBeFalsy();
    });

    it('should show the warning when a product profile should also export variants', async () => {
        await wrapper.find('.sw-import-export-exporter__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        const defaultProduct = await wrapper.find('.sw-select-option--0 .sw-select-result__result-item-text');
        expect(defaultProduct.text()).toBe('Default product');

        await defaultProduct.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('Default product');

        await wrapper.setData({
            config: {
                parameters: {
                    includeVariants: true,
                },
            },
        });
        await flushPromises();

        const variantsWarning = wrapper.find('.sw-import-export-exporter__variants-warning');

        expect(variantsWarning.exists()).toBeTruthy();
        expect(variantsWarning.text()).toContain('sw-import-export.exporter.variantsWarning');
    });

    it('should show a warning which contains an open modal link', async () => {
        await wrapper.find('.sw-import-export-exporter__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0 .sw-select-result__result-item-text').trigger('click');

        await wrapper.setData({
            config: {
                parameters: {
                    includeVariants: true,
                },
            },
        });
        await flushPromises();

        const variantsWarningLink = wrapper.findAll(
            '.sw-import-export-exporter__variants-warning .sw-import-export-exporter__link',
        );
        expect(variantsWarningLink.at(0).exists()).toBeTruthy();
        expect(variantsWarningLink.at(0).text()).toContain('sw-import-export.exporter.directExportVariantsLabel');

        expect(variantsWarningLink.at(1).exists()).toBeTruthy();
        expect(variantsWarningLink.at(1).text()).toContain(
            'sw-import-export.exporter.directExportPropertiesLabel',
        );
    });

    it('should show a modal which only contains configurator settings profiles', async () => {
        await wrapper.find('.sw-import-export-exporter__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0 .sw-select-result__result-item-text').trigger('click');

        await wrapper.setData({
            config: {
                parameters: {
                    includeVariants: true,
                },
            },
        });
        await flushPromises();

        const variantsWarningLink = wrapper.find(
            '.sw-import-export-exporter__variants-warning .sw-import-export-exporter__link',
        );
        await variantsWarningLink.trigger('click');

        const modalExporter = wrapper.findAllComponents('.sw-import-export-exporter').at(0);
        expect(modalExporter.exists()).toBeTruthy();
        expect(modalExporter.props().sourceEntity).toBe('product_configurator_setting');
    });

    it('should show all profiles when sourceEntity is empty', async () => {
        await wrapper.setProps({ sourceEntity: '' });

        await wrapper.find('.sw-import-export-exporter__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        const results = wrapper.findAll('.sw-highlight-text');
        const resultNames = [];
        results.forEach(result => resultNames.push(result.text()));

        expect(resultNames).toContain('Default product');
        expect(resultNames).toContain('Default configurator settings');
        expect(resultNames).toContain('Default category');
        expect(resultNames).toContain('Default media');
    });

    it('should show only matching profiles when sourceEntity property is set', async () => {
        await wrapper.setProps({ sourceEntity: 'product_configurator_setting' });
        await flushPromises();

        await wrapper.find('.sw-import-export-exporter__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        const results = await wrapper.findAll('.sw-highlight-text');
        const resultNames = [];
        results.forEach(result => resultNames.push(result.text()));

        expect(resultNames).not.toContain('Default product');
        expect(resultNames).toContain('Default configurator settings');
        expect(resultNames).not.toContain('Default category');
        expect(resultNames).not.toContain('Default media');
    });

    it('should throw an warning if the import fails hard', async () => {
        await wrapper.setData({
            selectedProfileId: 'a1b2c3d4e5',
            config: {
                error: true,
            },
        });


        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onStartProcess();
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'This is an error code: This is an detailed error message',
        });

        wrapper.vm.createNotificationError.mockRestore();
    });
});
