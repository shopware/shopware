/**
 * @sw-package discovery
 */
import { mount, flushPromises } from '@vue/test-utils';
import 'src/module/sw-settings-agentic-commerce/page/sw-settings-agentic-commerce';

interface MyExtension {
    name: string;
    source?: string;
    type?: string;
}

type ComponentVm = {
    reloadPage: () => void;
    onInstallExtension: () => Promise<void>;
    createNotificationError: (config: { message: string }) => void;
};

function createServices(myExtensions: MyExtension[] = []) {
    return {
        shopwareExtensionService: {
            installAndActivateExtension: jest.fn().mockResolvedValue(undefined),
        },
        extensionStoreActionService: {
            getMyExtensions: jest.fn().mockResolvedValue(myExtensions),
            downloadExtension: jest.fn().mockResolvedValue(undefined),
        },
        cacheApiService: {
            clear: jest.fn().mockResolvedValue(undefined),
        },
    };
}

let services: ReturnType<typeof createServices>;

// Core renders the static "extension not installed" state — it never inspects the
// installed bundles. The SwagAgenticCommerce plugin override drives the installed /
// prepared / ready states (covered by the plugin's own tests).
async function createWrapper(myExtensions: MyExtension[] = []) {
    services = createServices(myExtensions);

    return mount(
        await wrapTestComponent('sw-settings-agentic-commerce', {
            sync: true,
        }),
        {
            global: {
                provide: services,
                mocks: {
                    $t: (path: string, values?: Record<string, unknown>) => {
                        if (values) {
                            return `${path} ${JSON.stringify(values)}`;
                        }

                        return path;
                    },
                },
                stubs: {
                    'sw-page': {
                        template: '<div class="sw-page"><slot name="content"></slot></div>',
                    },
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot></slot></div>',
                    },
                    'mt-card': {
                        template: '<div class="mt-card"><slot></slot></div>',
                    },
                    'mt-button': {
                        props: ['disabled', 'isLoading'],
                        template:
                            '<button :disabled="disabled" :data-loading="isLoading" @click="$emit(\'click\')"><slot></slot></button>',
                    },
                    'mt-icon': {
                        props: ['name'],
                        template: '<span class="mt-icon" :data-icon="name"></span>',
                    },
                    'mt-progress-bar': {
                        props: ['modelValue'],
                        template: '<div class="mt-progress-bar" :data-value="modelValue"></div>',
                    },
                    'sw-search-bar': true,
                    'sw-extension-component-section': true,
                },
            },
        },
    );
}

describe('module/sw-settings-agentic-commerce/page/sw-settings-agentic-commerce', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should expose exactly two readiness steps', async () => {
        const wrapper = await createWrapper();

        const steps = wrapper.vm.steps as { key: string }[];
        expect(steps.map((step) => step.key)).toEqual(['extension', 'prepareSalesChannels']);
        expect(wrapper.vm.totalStepCount).toBe(2);
    });

    describe('static not-installed state', () => {
        it('reports no completed steps and is not ready', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.completedStepCount).toBe(0);
            expect(wrapper.vm.progressValue).toBe(0);
            expect(wrapper.vm.isReady).toBe(false);
        });

        it('renders the install extension button instead of the complete label', async () => {
            const wrapper = await createWrapper();

            const stepText = wrapper.text();
            expect(stepText).toContain('sw-settings-agentic-commerce.readiness.steps.extension.action');
            expect(stepText).not.toContain('sw-settings-agentic-commerce.readiness.stepComplete');
        });

        it('disables the prepare sales channels action', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.canPrepareSalesChannels).toBe(false);

            const buttons = wrapper.findAll('button');
            const prepareButton = buttons.find((button) =>
                button.text().includes('sw-settings-agentic-commerce.readiness.steps.prepareSalesChannels.action'),
            );

            expect(prepareButton).toBeTruthy();
            expect(prepareButton?.attributes('disabled')).toBeDefined();
        });

        it('uses the inactive subtitle', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.readinessSubtitle).toBe('sw-settings-agentic-commerce.readiness.subtitleInactive');
        });

        it('reports the extension as not installed in the shop readiness card', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.extensionStatusLabel).toBe('sw-settings-agentic-commerce.shopReadiness.extensionNotInstalled');
            expect(wrapper.text()).toContain('sw-settings-agentic-commerce.shopReadiness.extensionNotInstalled');
        });
    });

    describe('installing the extension', () => {
        it('downloads, installs and activates a store-sourced extension, then clears cache and reloads', async () => {
            const wrapper = await createWrapper([{ name: 'SwagAgenticCommerce', source: 'store', type: 'plugin' }]);
            const reloadSpy = jest.spyOn(wrapper.vm as unknown as ComponentVm, 'reloadPage').mockImplementation(() => {});

            await (wrapper.vm as unknown as ComponentVm).onInstallExtension();

            expect(services.extensionStoreActionService.downloadExtension).toHaveBeenCalledWith('SwagAgenticCommerce');
            expect(services.shopwareExtensionService.installAndActivateExtension).toHaveBeenCalledWith(
                'SwagAgenticCommerce',
                'plugin',
            );
            expect(services.cacheApiService.clear).toHaveBeenCalled();
            expect(reloadSpy).toHaveBeenCalled();
        });

        it('does not download a locally sourced extension before installing', async () => {
            const wrapper = await createWrapper([{ name: 'SwagAgenticCommerce', source: 'local', type: 'plugin' }]);
            jest.spyOn(wrapper.vm as unknown as ComponentVm, 'reloadPage').mockImplementation(() => {});

            await (wrapper.vm as unknown as ComponentVm).onInstallExtension();

            expect(services.extensionStoreActionService.downloadExtension).not.toHaveBeenCalled();
            expect(services.shopwareExtensionService.installAndActivateExtension).toHaveBeenCalledWith(
                'SwagAgenticCommerce',
                'plugin',
            );
        });

        it('falls back to the plugin type when the extension is not listed', async () => {
            const wrapper = await createWrapper([]);
            jest.spyOn(wrapper.vm as unknown as ComponentVm, 'reloadPage').mockImplementation(() => {});

            await (wrapper.vm as unknown as ComponentVm).onInstallExtension();

            expect(services.extensionStoreActionService.downloadExtension).not.toHaveBeenCalled();
            expect(services.shopwareExtensionService.installAndActivateExtension).toHaveBeenCalledWith(
                'SwagAgenticCommerce',
                'plugin',
            );
        });

        it('marks the install as in progress while the flow runs', async () => {
            const wrapper = await createWrapper([{ name: 'SwagAgenticCommerce', source: 'local' }]);
            jest.spyOn(wrapper.vm as unknown as ComponentVm, 'reloadPage').mockImplementation(() => {});

            expect(wrapper.vm.isInstallingExtension).toBe(false);

            void (wrapper.vm as unknown as ComponentVm).onInstallExtension();
            expect(wrapper.vm.isInstallingExtension).toBe(true);

            await flushPromises();
        });

        it('resets the loading state and notifies the user on failure', async () => {
            const wrapper = await createWrapper([{ name: 'SwagAgenticCommerce', source: 'local' }]);
            const notifySpy = jest
                .spyOn(wrapper.vm as unknown as ComponentVm, 'createNotificationError')
                .mockImplementation(() => {});
            const reloadSpy = jest.spyOn(wrapper.vm as unknown as ComponentVm, 'reloadPage').mockImplementation(() => {});
            services.shopwareExtensionService.installAndActivateExtension.mockRejectedValue(new Error('failed'));

            await (wrapper.vm as unknown as ComponentVm).onInstallExtension();

            expect(notifySpy).toHaveBeenCalled();
            expect(reloadSpy).not.toHaveBeenCalled();
            expect(wrapper.vm.isInstallingExtension).toBe(false);
        });

        it('starts the install flow when the install button is clicked', async () => {
            const wrapper = await createWrapper([{ name: 'SwagAgenticCommerce', source: 'local' }]);
            jest.spyOn(wrapper.vm as unknown as ComponentVm, 'reloadPage').mockImplementation(() => {});

            const installButton = wrapper
                .findAll('button')
                .find((button) => button.text().includes('sw-settings-agentic-commerce.readiness.steps.extension.action'));

            if (!installButton) {
                throw new Error('Install button not found');
            }

            await installButton.trigger('click');
            await flushPromises();

            expect(services.shopwareExtensionService.installAndActivateExtension).toHaveBeenCalled();
        });
    });

    describe('shop readiness counts', () => {
        it('defaults both sales channel counts to zero', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.vm.preparedSalesChannelCount).toBe(0);
            expect(wrapper.vm.agenticSalesChannelCount).toBe(0);

            const facts = wrapper.findAll('.sw-settings-agentic-commerce__fact dd');
            expect(facts[1].text()).toBe('0');
            expect(facts[2].text()).toBe('0');
        });

        it('renders the counts when the data properties are updated', async () => {
            const wrapper = await createWrapper();

            await wrapper.setData({
                preparedSalesChannelCount: 3,
                agenticSalesChannelCount: 2,
            });

            const facts = wrapper.findAll('.sw-settings-agentic-commerce__fact dd');
            expect(facts[1].text()).toBe('3');
            expect(facts[2].text()).toBe('2');
        });
    });
});
