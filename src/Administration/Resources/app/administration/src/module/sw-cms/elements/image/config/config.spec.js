/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';
import selectMtSelectOptionByText from 'test/_helper_/select-mt-select-by-text';

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-cms-el-config-image', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    cmsService: Shopware.Service('cmsService'),
                    repositoryFactory: {
                        create: () => {
                            return {
                                search: () => Promise.resolve(),
                            };
                        },
                    },
                },
                stubs: {
                    'sw-text-field': true,
                    'sw-cms-mapping-field': await wrapTestComponent('sw-cms-mapping-field'),
                    'sw-media-upload-v2': true,
                    'sw-upload-listener': true,
                    'sw-dynamic-url-field': true,

                    'sw-media-modal-v2': true,
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'sw-cms-inherit-wrapper': {
                        template: '<div><slot :isInherited="false"></slot></div>',
                        props: [
                            'field',
                            'element',
                            'contentEntity',
                            'label',
                        ],
                    },
                    'sw-container': await wrapTestComponent('sw-container'),
                },
            },
            props: {
                element: {
                    config: {
                        media: {
                            source: 'static',
                            value: null,
                            required: true,
                            entity: {
                                name: 'media',
                            },
                        },
                        displayMode: {
                            source: 'static',
                            value: 'standard',
                        },
                        url: {
                            source: 'static',
                            value: null,
                        },
                        ariaLabel: {
                            source: 'static',
                            value: null,
                        },
                        newTab: {
                            source: 'static',
                            value: false,
                        },
                        minHeight: {
                            source: 'static',
                            value: '340px',
                        },
                        verticalAlign: {
                            source: 'static',
                            value: null,
                        },
                        horizontalAlign: {
                            source: 'static',
                            value: null,
                        },
                        isDecorative: {
                            source: 'static',
                            value: false,
                        },
                        fetchPriorityHigh: {
                            source: 'static',
                            value: false,
                        },
                    },
                    data: {},
                },
                defaultConfig: {},
            },
        },
    );
}

describe('src/module/sw-cms/elements/image/config', () => {
    beforeAll(async () => {
        await setupCmsEnvironment();
    });

    it('should keep minHeight value when changing display mode', async () => {
        const wrapper = await createWrapper();

        await selectMtSelectOptionByText(
            wrapper,
            'sw-cms.elements.general.config.label.displayModeCover',
            '.sw-cms-el-config-image__display-mode input',
        );

        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');

        await selectMtSelectOptionByText(
            wrapper,
            'sw-cms.elements.general.config.label.displayModeStandard',
            '.sw-cms-el-config-image__display-mode input',
        );

        // Should still have the previous value
        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');
    });

    it('should append px to a unitless min height value', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onChangeMinHeight('500');
        expect(wrapper.vm.element.config.minHeight.value).toBe('500px');

        wrapper.vm.onChangeMinHeight('260.5');
        expect(wrapper.vm.element.config.minHeight.value).toBe('260.5px');
    });

    it('should keep an explicitly given unit on the min height value', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onChangeMinHeight('340px');
        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');

        wrapper.vm.onChangeMinHeight('20rem');
        expect(wrapper.vm.element.config.minHeight.value).toBe('20rem');
    });

    it('should clear the min height value when emptied', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onChangeMinHeight('');
        expect(wrapper.vm.element.config.minHeight.value).toBe('');

        wrapper.vm.onChangeMinHeight(null);
        expect(wrapper.vm.element.config.minHeight.value).toBe('');
    });

    it('should change the isDecorative value', async () => {
        const wrapper = await createWrapper();
        const isDecorativeSwitch = wrapper.find('.sw-cms-el-config-image__is-decorative input');

        await isDecorativeSwitch.setValue(true);

        expect(wrapper.vm.element.config.isDecorative.value).toBe(true);

        await isDecorativeSwitch.setValue(false);

        expect(wrapper.vm.element.config.isDecorative.value).toBe(false);
    });
});
