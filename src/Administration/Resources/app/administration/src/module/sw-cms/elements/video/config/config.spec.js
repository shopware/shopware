/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import { setupCmsEnvironment } from 'src/module/sw-cms/test-utils';
import selectMtSelectOptionByText from 'test/_helper_/select-mt-select-by-text';

async function createWrapper() {
    return mount(await Shopware.Component.build('sw-cms-el-config-video'), {
        attachTo: document.body,
        global: {
            renderStubDefaultSlot: true,
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
                'mt-text-field': {
                    template: `
                        <input
                            class="mt-text-field"
                            :value="modelValue"
                            :placeholder="placeholder"
                            @input="$emit('update:modelValue', $event.target.value)"
                        />
                    `,
                    props: [
                        'modelValue',
                        'placeholder',
                        'disabled',
                    ],
                },
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
                    ariaLabel: {
                        source: 'static',
                        value: null,
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
                    autoPlay: {
                        source: 'static',
                        value: false,
                    },
                    muted: {
                        source: 'static',
                        value: false,
                    },
                    loop: {
                        source: 'static',
                        value: false,
                    },
                    playsInline: {
                        source: 'static',
                        value: false,
                    },
                    showControls: {
                        source: 'static',
                        value: true,
                    },
                    showCover: {
                        source: 'static',
                        value: true,
                    },
                },
                data: {},
            },
            defaultConfig: {},
        },
    });
}

describe('src/module/sw-cms/elements/video/config', () => {
    let wrapper;

    beforeAll(async () => {
        await setupCmsEnvironment();
        if (!Shopware.Component.getComponentRegistry().has('sw-cms-el-config-video')) {
            const { default: swCmsElConfigVideo } = await import('src/module/sw-cms/elements/video/config');
            Shopware.Component.register('sw-cms-el-config-video', swCmsElConfigVideo);
        }
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }
    });

    it('should keep minHeight value when changing display mode', async () => {
        wrapper = await createWrapper();

        await selectMtSelectOptionByText(
            wrapper,
            'sw-cms.elements.general.config.label.displayModeCover',
            '.sw-cms-el-config-video__display-mode input',
        );

        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');

        await selectMtSelectOptionByText(
            wrapper,
            'sw-cms.elements.general.config.label.displayModeStandard',
            '.sw-cms-el-config-video__display-mode input',
        );

        // Should still have the previous value
        expect(wrapper.vm.element.config.minHeight.value).toBe('340px');
    });

    it('should enable muted and disable showCover when enabling autoPlay', async () => {
        wrapper = await createWrapper();
        const autoPlaySwitch = wrapper.find('.sw-cms-el-config-video__checkboxes-auto-play input');

        await autoPlaySwitch.setValue(true);

        expect(wrapper.vm.element.config.autoPlay.value).toBe(true);
        expect(wrapper.vm.element.config.muted.value).toBe(true);
        expect(wrapper.vm.element.config.showCover.value).toBe(false);
    });

    it('should set placeholders for ariaLabel and minHeight', async () => {
        wrapper = await createWrapper();

        const ariaLabelInput = wrapper.find('.sw-cms-el-config-video__aria-label .mt-text-field');
        expect(ariaLabelInput.attributes('placeholder')).toBe(
            wrapper.vm.$t('sw-cms.elements.video.config.placeholder.ariaLabel'),
        );

        await selectMtSelectOptionByText(
            wrapper,
            'sw-cms.elements.general.config.label.displayModeCover',
            '.sw-cms-el-config-video__display-mode input',
        );

        const placeholders = wrapper.findAll('.mt-text-field').map((input) => input.attributes('placeholder'));
        expect(placeholders).toContain(wrapper.vm.$t('sw-cms.elements.video.config.placeholder.minHeight'));
    });
});
