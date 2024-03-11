/**
 * @package services-settings
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

async function createWrapper() {
    return mount(await wrapTestComponent('sw-import-export-importer', { sync: true }), {
        global: {
            directives: {
                popover: Shopware.Directive.getByName('popover'),
            },
            stubs: {
                'sw-import-export-importer': await wrapTestComponent('sw-import-export-importer', { sync: true }),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-alert': await wrapTestComponent('sw-alert'),
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                            <slot name="body">
                                <div class="sw-modal__body">
                                    <slot name="modal-loader">
                                        <sw-loader v-if="isLoading" />
                                    </slot>
                                    <slot>
                                    </slot>
                                </div>
                            </slot>
                        </div>
                    `,
                },
            },
            provide: {
                importExport: {
                    import: (profileId, importFile, cb, config) => {
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
}

describe('components/sw-import-export-importer', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should not show the warning when nothing is selected', async () => {
        expect(wrapper.find('.sw-import-export-importer__variants-warning').exists()).toBeFalsy();
    });

    it('should not show the warning when a product profile without variants is selected', async () => {
        await wrapper.find('.sw-import-export-importer__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        const defaultProduct = document.body.querySelector('.sw-select-option--0');
        expect(defaultProduct.querySelector('.sw-highlight-text').textContent).toBe('Default product');

        await defaultProduct.click();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('Default product');
        expect(wrapper.find('.sw-import-export-importer__variants-warning').exists()).toBeFalsy();
    });

    it('should not show the warning when a product profile should not import variants', async () => {
        await wrapper.find('.sw-import-export-importer__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        const defaultProduct = document.body.querySelector('.sw-select-option--0');
        expect(defaultProduct.querySelector('.sw-highlight-text').textContent).toBe('Default product');

        await defaultProduct.click();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('Default product');

        const variantsWarning = wrapper.find('.sw-import-export-importer__variants-warning');

        expect(variantsWarning.exists()).toBeFalsy();
    });

    it('should show the warning when a product profile should also import variants', async () => {
        await wrapper.find('.sw-import-export-importer__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        const defaultProduct = document.body.querySelector('.sw-select-option--0');
        expect(defaultProduct.querySelector('.sw-highlight-text').textContent).toBe('Default product');

        await defaultProduct.click();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('Default product');

        await wrapper.setData({
            config: {
                includeVariants: true,
            },
        });
        await flushPromises();

        const variantsWarning = wrapper.find('.sw-import-export-importer__variants-warning');

        expect(variantsWarning.exists()).toBeTruthy();
        expect(variantsWarning.text()).toContain('sw-import-export.importer.variantsWarning');
    });

    it('should show a warning which contains an open modal link', async () => {
        await wrapper.find('.sw-import-export-importer__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        await document.body.querySelector('.sw-select-option--0').click();

        await wrapper.setData({
            config: {
                includeVariants: true,
            },
        });
        await flushPromises();

        const variantsWarningLinks = wrapper.findAll(
            '.sw-import-export-importer__variants-warning .sw-import-export-importer__link',
        );
        expect(variantsWarningLinks.at(0).exists()).toBeTruthy();
        expect(variantsWarningLinks.at(0).text()).toContain(
            'sw-import-export.importer.directImportVariantsLabel',
        );

        expect(variantsWarningLinks.at(1).exists()).toBeTruthy();
        expect(variantsWarningLinks.at(1).text()).toContain(
            'sw-import-export.importer.directImportPropertiesLabel',
        );
    });

    it('should show a modal with an importer', async () => {
        await wrapper.find('.sw-import-export-importer__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        await document.body.querySelector('.sw-select-option--0').click();

        await wrapper.setData({
            config: {
                includeVariants: true,
            },
        });
        await flushPromises();

        const variantsWarningLink = wrapper.find(
            '.sw-import-export-importer__variants-warning .sw-import-export-importer__link',
        );
        await variantsWarningLink.trigger('click');
        await flushPromises();

        const modalExporter = wrapper.findAll('.sw-import-export-importer').at(1);

        expect(modalExporter.exists()).toBeTruthy();
    });

    it('should show a modal which only contains configurator settings profiles', async () => {
        await wrapper.find('.sw-import-export-importer__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        await document.body.querySelector('.sw-select-option--0').click();

        await wrapper.setData({
            config: {
                includeVariants: true,
            },
        });
        await flushPromises();

        const variantsWarningLink = wrapper.find(
            '.sw-import-export-importer__variants-warning .sw-import-export-importer__link',
        );
        await variantsWarningLink.trigger('click');
        await flushPromises();

        const modalExporter = wrapper.findComponent('.sw-import-export-importer .sw-import-export-importer');

        expect(modalExporter.props().sourceEntity).toBe('product_configurator_setting');
    });

    it('should show all profiles when sourceEntity is empty', async () => {
        await wrapper.setProps({ sourceEntity: '' });

        await wrapper.find('.sw-import-export-importer__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        const results = document.body.querySelectorAll('.sw-select-result');

        expect(results.item(0).textContent).toContain('Default product');
        expect(results.item(1).textContent).toContain('Default configurator settings');
        expect(results.item(2).textContent).toContain('Default category');
        expect(results.item(3).textContent).toContain('Default media');
    });

    it('should show only matching profiles when sourceEntity property has been set', async () => {
        await wrapper.setProps({ sourceEntity: 'product_configurator_setting' });

        await wrapper.find('.sw-import-export-importer__profile-select .sw-select__selection').trigger('click');
        await flushPromises();

        const results = document.body.querySelectorAll('.sw-select-result');

        expect(results.item(0).textContent).toContain('Default configurator settings');
        expect(results).toHaveLength(1);
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
