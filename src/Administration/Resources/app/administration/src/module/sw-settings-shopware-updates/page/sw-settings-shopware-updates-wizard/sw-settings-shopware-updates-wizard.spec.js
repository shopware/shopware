/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import useSession from 'src/app/composables/use-session';
import useSnackbar from 'src/app/composables/use-snackbar';

jest.mock('src/app/composables/use-snackbar', () => ({
    __esModule: true,
    default: jest.fn(),
}));

describe('module/sw-settings-shopware-updates/page/sw-settings-shopware-updates-wizard', () => {
    let wrapper;
    const mockSnackbar = {
        addSnackbar: jest.fn(),
        removeSnackbar: jest.fn(),
    };

    async function createWrapper(updateServiceOverrides = {}) {
        return mount(
            await wrapTestComponent('sw-settings-shopware-updates-wizard', {
                sync: true,
            }),
            {
                global: {
                    renderStubDefaultSlot: true,
                    provide: {
                        updateService: {
                            checkForUpdates: () =>
                                Promise.resolve({
                                    extensions: [],
                                    title: 'Release 6.4.18.0',
                                    body: 'This is a test release',
                                    date: '2022-12-08T09:04:06.000+00:00',
                                    version: '6.4.18.0',
                                    fixedVulnerabilities: [],
                                }),
                            checkLicense: () =>
                                Promise.resolve({
                                    isValid: false,
                                }),
                            deactivateExtensions: () => {
                                const error = new Error();

                                error.response = {
                                    data: {
                                        errors: [
                                            {
                                                code: 'THEME__THEME_ASSIGNMENT',
                                                meta: {
                                                    parameters: {
                                                        themeName: '7305fd18-09ee-4d2c-afd4-b9fb90ad8508',
                                                        assignments: 'afe95e1e-cc8e-487b-863a-94c5c4e51fa6',
                                                    },
                                                },
                                            },
                                        ],
                                    },
                                };

                                return Promise.reject(error);
                            },
                            extensionCompatibility: () => Promise.resolve([]),
                            downloadRecovery: () => Promise.resolve([]),
                            ...updateServiceOverrides,
                        },
                    },
                    mocks: {
                        $route: {
                            name: '',
                            meta: {
                                parentPath: 'sw.settings.index',
                                $module: {
                                    type: 'core',
                                    name: 'settings',
                                    title: 'sw-settings.general.mainMenuItemGeneral',
                                    color: '#9AA8B5',
                                    icon: 'default-action-settings',
                                    favicon: 'icon-module-settings.svg',
                                    routes: {
                                        index: {
                                            path: '/sw/settings/index',
                                            icon: 'default-action-settings',
                                            name: 'sw.settings.index',
                                            type: 'core',
                                            components: {
                                                default: {
                                                    _custom: {
                                                        type: 'function',
                                                        display: '<span>ƒ</span> VueComponent(options)',
                                                    },
                                                },
                                            },
                                            isChildren: false,
                                            routeKey: 'index',
                                        },
                                    },
                                    navigation: [
                                        {
                                            id: 'sw-settings',
                                            label: 'sw-settings.general.mainMenuItemGeneral',
                                            color: '#9AA8B5',
                                            icon: 'default-action-settings',
                                            path: 'sw.settings.index',
                                            position: 80,
                                            children: [],
                                        },
                                    ],
                                },
                            },
                            params: {
                                id: '',
                            },
                        },
                        $i18n: {
                            locale: 'de-De',
                        },
                    },
                    stubs: {
                        'sw-page': await wrapTestComponent('sw-page'),
                        'sw-search-bar': {
                            template: '<div></div>',
                        },
                        'sw-notification-center': {
                            template: '<div></div>',
                        },
                        'sw-help-center': true,
                        'sw-tooltip': {
                            template: '<div></div>',
                        },
                        'sw-card-view': await wrapTestComponent('sw-card-view'),
                        'sw-ignore-class': true,
                        'sw-settings-shopware-updates-extensions': {
                            template: '<div class="sw-settings-shopware-updates-extensions"></div>',
                        },
                        'sw-loader': {
                            template: '<div></div>',
                        },
                        'router-link': {
                            template: '<a></a>',
                        },
                        'sw-app-actions': true,
                        'sw-extension-component-section': true,
                        'sw-error-summary': true,
                        'sw-modal': {
                            template: '<div><slot></slot><slot name="modal-footer"></slot></div>',
                        },
                        'mt-banner': true,
                        'sw-external-link': {
                            template: '<a class="sw-external-link" :href="$attrs.href"><slot></slot></a>',
                        },
                        'mt-progress-bar': true,
                        'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                        'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', {
                            sync: true,
                        }),
                        'sw-field-error': true,
                        'sw-base-field': true,
                        'sw-app-topbar-button': true,
                        'sw-app-topbar-sidebar': true,
                        'sw-help-center-v2': true,
                        'sw-empty-state': true,

                        'sw-radio-field': true,
                        'sw-ai-copilot-badge': true,
                        'sw-provide': true,
                    },
                    attachTo: document.body,
                },
            },
        );
    }

    beforeEach(async () => {
        Shopware.Application.view.deleteReactive = () => {};
        useSession().currentLocale.value = null;
        Shopware.Store.get('context').app.config.version = '6.4.17.2';
        mockSnackbar.addSnackbar.mockClear();
        jest.mocked(useSnackbar).mockReturnValue(mockSnackbar);
        wrapper = await createWrapper();

        await flushPromises();
    });

    it('shows a critical banner when the Shopware license check fails', async () => {
        const licenseBanner = wrapper.get('.sw-settings-shopware-updates-wizard__license-banner');

        expect(licenseBanner.attributes('variant')).toBe('critical');

        wrapper.vm.licenseValid = true;
        await flushPromises();

        expect(wrapper.find('.sw-settings-shopware-updates-wizard__license-banner').exists()).toBe(false);
    });

    it('should disable the button if the license check fails', async () => {
        const button = wrapper.findByText('button', 'sw-settings-shopware-updates.infos.startUpdate');

        expect(button.attributes('aria-disabled')).toBe('true');

        await button.trigger('click');

        expect(wrapper.vm.updateModalShown).toBe(false);
    });

    it('should show the correct error message, when theme deactivation fails', async () => {
        const stopUpdateProcessSpy = jest.spyOn(wrapper.vm, 'stopUpdateProcess');
        const createNotificationWarningSpy = jest.spyOn(wrapper.vm, 'createNotificationWarning');

        wrapper.vm.deactivateExtensions(0);
        await flushPromises();

        expect(stopUpdateProcessSpy).toHaveBeenCalled();
        expect(createNotificationWarningSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                message: expect.stringContaining('sw-extension.errors.messageDeactivationFailedThemeAssignment'),
            }),
        );
    });

    it('deactivate extensions success', async () => {
        wrapper.vm.updateService.deactivateExtensions = () => {
            return Promise.resolve({
                offset: 0,
                total: 0,
            });
        };

        const redirectSpy = jest.fn();
        wrapper.vm.redirectToPage = redirectSpy;

        await wrapper.vm.deactivateExtensions(0);

        expect(redirectSpy).toHaveBeenCalledWith(`${Shopware.Context.api.basePath}/shopware-installer.phar.php`);
    });

    it('deactivate extensions success forwards the admin locale to the recovery tool', async () => {
        useSession().currentLocale.value = 'de-DE';

        wrapper.vm.updateService.deactivateExtensions = () => {
            return Promise.resolve({
                offset: 0,
                total: 0,
            });
        };

        const redirectSpy = jest.fn();
        wrapper.vm.redirectToPage = redirectSpy;

        await wrapper.vm.deactivateExtensions(0);

        expect(redirectSpy).toHaveBeenCalledWith(
            `${Shopware.Context.api.basePath}/shopware-installer.phar.php?language=de-DE`,
        );
    });

    it('buildRecoveryUrl appends the admin locale as the language parameter', () => {
        useSession().currentLocale.value = 'en-GB';

        expect(wrapper.vm.buildRecoveryUrl()).toBe(
            `${Shopware.Context.api.basePath}/shopware-installer.phar.php?language=en-GB`,
        );
    });

    it('buildRecoveryUrl omits the language parameter when no locale is set', () => {
        useSession().currentLocale.value = null;

        expect(wrapper.vm.buildRecoveryUrl()).toBe(`${Shopware.Context.api.basePath}/shopware-installer.phar.php`);
    });

    it('deactivate extensions success loops to disable all', async () => {
        wrapper.vm.updateService.deactivateExtensions = (offset) => {
            if (offset === 0) {
                return Promise.resolve({
                    offset: 1,
                    total: 2,
                });
            }

            return Promise.resolve({
                offset: 1,
                total: 1,
            });
        };

        const redirectSpy = jest.fn();
        wrapper.vm.redirectToPage = redirectSpy;

        const updateCallSpy = jest.spyOn(wrapper.vm.updateService, 'deactivateExtensions');

        await wrapper.vm.deactivateExtensions(0);
        await flushPromises();

        expect(redirectSpy).toHaveBeenCalledWith(`${Shopware.Context.api.basePath}/shopware-installer.phar.php`);
        expect(updateCallSpy).toHaveBeenCalledTimes(2);
    });

    it('download recovery should disable extensions', async () => {
        const disableExtensionsSpy = jest.spyOn(wrapper.vm, 'deactivateExtensions');

        await wrapper.vm.downloadRecovery();
        expect(wrapper.vm.progressbarValue).toBe(0);

        expect(disableExtensionsSpy).toHaveBeenCalled();
    });

    it('download recovery should on error notification', async () => {
        wrapper.vm.updateService.downloadRecovery = () => Promise.reject(new Error('error'));

        const createNotificationErrorSpy = jest.spyOn(wrapper.vm, 'createNotificationError');

        await wrapper.vm.downloadRecovery();
        await flushPromises();

        expect(wrapper.vm.progressbarValue).toBe(0);
        expect(createNotificationErrorSpy).toHaveBeenCalled();
    });

    it('start update should download recovery', async () => {
        const downloadRecoverySpy = jest.spyOn(wrapper.vm, 'downloadRecovery');

        await wrapper.vm.startUpdateProcess();
        expect(downloadRecoverySpy).toHaveBeenCalled();

        expect(wrapper.emitted('update-started')).toBeTruthy();
        expect(wrapper.emitted('update-started')).toHaveLength(1);
    });

    it('shows the installed and the latest version in the version card', async () => {
        const versionCard = wrapper.get('.sw-settings-shopware-updates-version');

        expect(versionCard.get('.sw-settings-shopware-updates-version__current-version').text()).toBe('6.4.17.2');
        expect(versionCard.get('.sw-settings-shopware-updates-version__new-version').text()).toBe('6.4.18.0');
        expect(versionCard.get('.sw-settings-shopware-updates-version__changelog-link').attributes('href')).toBe(
            'https://github.com/shopware/shopware/releases/',
        );
    });

    it('click on update button', async () => {
        wrapper.vm.updateService.deactivateExtensions = () => {
            return Promise.resolve({
                offset: 1,
                total: 1,
            });
        };
        wrapper.vm.licenseValid = true;

        expect(wrapper.vm.updatePossible).toBe(true);
        expect(wrapper.vm.updaterIsRunning).toBe(false);
        expect(wrapper.vm.updateModalShown).toBe(false);

        await flushPromises();

        await wrapper.get('.sw-settings-shopware-updates-wizard__start-update').trigger('click');
        await flushPromises();

        expect(wrapper.vm.updateModalShown).toBe(true);

        expect(wrapper.find('.sw-settings-shopware-updates-check__start-update').exists()).toBe(true);
        expect(wrapper.get('.sw-settings-shopware-updates-cli-method__command').text()).toContain(
            'shopware-cli project upgrade',
        );
        expect(wrapper.get('.sw-settings-shopware-updates-cli-method__install-link').attributes('href')).toBe(
            'https://developer.shopware.com/docs/products/tools/cli/',
        );

        await wrapper.get('.sw-settings-shopware-updates-check__start-update-backup-checkbox input').setChecked(true);

        const redirectSpy = jest.fn();
        wrapper.vm.redirectToPage = redirectSpy;

        await wrapper.get('.sw-settings-shopware-updates-check__start-update-button').trigger('click');

        expect(wrapper.emitted('update-started')).toBeTruthy();
        expect(wrapper.emitted('update-started')).toHaveLength(1);

        await flushPromises();

        expect(redirectSpy).toHaveBeenCalledWith(`${Shopware.Context.api.basePath}/shopware-installer.phar.php`);
    });

    it('still shows the update but disables the web installer when auto updates are disabled', async () => {
        wrapper.vm.licenseValid = true;
        wrapper.vm.autoUpdateEnabled = false;
        await flushPromises();

        expect(wrapper.find('.sw-settings-shopware-updates-version').exists()).toBe(true);
        expect(wrapper.get('.sw-settings-shopware-updates-cli-method__command').text()).toContain(
            'shopware-cli project upgrade',
        );
        expect(wrapper.get('.sw-settings-shopware-updates-wizard__start-update').attributes('aria-disabled')).toBe('true');
        expect(wrapper.vm.updateButtonTooltipMessage).toBe('sw-settings-shopware-updates.infos.autoUpdateDisabled');
    });

    it('disables the web installer on cluster setups', async () => {
        wrapper.vm.licenseValid = true;
        wrapper.vm.clusterSetup = true;
        await flushPromises();

        expect(wrapper.get('.sw-settings-shopware-updates-wizard__start-update').attributes('aria-disabled')).toBe('true');
        expect(wrapper.vm.updateButtonTooltipMessage).toBe('sw-settings-shopware-updates.infos.clusterSetupDisabled');
    });

    it('recommends the Shopware CLI inside the version card', async () => {
        const versionCard = wrapper.get('.sw-settings-shopware-updates-wizard__version-card');

        expect(versionCard.get('.sw-settings-shopware-updates-methods-headline').text()).toBe(
            'sw-settings-shopware-updates.versionCard.methodsHeadline',
        );

        const cliMethod = versionCard.get('.sw-settings-shopware-updates-cli-method');

        expect(cliMethod.text()).toContain('sw-settings-shopware-updates.methodModal.cliDescription');
        expect(cliMethod.get('.sw-settings-shopware-updates-cli-method__command').text()).toContain(
            'shopware-cli project upgrade',
        );
        expect(cliMethod.get('.sw-settings-shopware-updates-cli-method__install-link').attributes('href')).toBe(
            'https://developer.shopware.com/docs/products/tools/cli/',
        );
    });

    it('shows the update status as a status indicator badge in the version card header', async () => {
        const badge = wrapper.get('.sw-settings-shopware-updates-version__status-badge');

        expect(badge.text()).toContain('sw-settings-shopware-updates.versionCard.badgeUpdateAvailable');
        expect(badge.attributes('status-indicator')).toBeDefined();
        expect(wrapper.vm.updateStatusBadgeVariant).toBe('attention');

        wrapper.vm.updateInfo = { version: null, changelog: null };
        await flushPromises();

        expect(wrapper.vm.updateStatusBadgeVariant).toBe('positive');
        expect(wrapper.vm.updateStatusBadgeLabel).toBe('sw-settings-shopware-updates.versionCard.badgeUpToDate');
    });

    it('shows only the version card with an up-to-date state when no update is available', async () => {
        wrapper.vm.updateInfo = { version: null, changelog: null };
        await flushPromises();

        const versionCard = wrapper.get('.sw-settings-shopware-updates-wizard__version-card');
        const upToDateState = versionCard.get('.sw-settings-shopware-updates-up-to-date');

        expect(upToDateState.find('.mt-empty-state__icon').exists()).toBe(true);
        expect(upToDateState.text()).toContain('sw-settings-shopware-updates.versionCard.upToDateTitle');
        expect(upToDateState.text()).toContain('sw-settings-shopware-updates.versionCard.upToDateDescription');
        expect(versionCard.find('.sw-settings-shopware-updates-version').exists()).toBe(false);
        expect(versionCard.find('.sw-settings-shopware-updates-methods-headline').exists()).toBe(false);
        expect(versionCard.find('.sw-settings-shopware-updates-cli-method').exists()).toBe(false);
        expect(versionCard.find('.sw-settings-shopware-updates-method-divider').exists()).toBe(false);
        expect(versionCard.find('.sw-settings-shopware-updates-web-installer').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-shopware-updates-extensions').exists()).toBe(false);
    });

    it('shows a checkmark on the copy button after copying and reverts after a delay', async () => {
        jest.useFakeTimers();

        try {
            jest.spyOn(Shopware.Utils.dom, 'copyStringToClipboard').mockResolvedValue();
            const copyButton = wrapper.get('.sw-settings-shopware-updates-cli-method__copy-button');

            await copyButton.trigger('click');
            await jest.advanceTimersByTimeAsync(0);

            expect(copyButton.find('[data-testid="mt-icon__regular-checkmark"]').exists()).toBe(true);

            await jest.advanceTimersByTimeAsync(2000);

            expect(copyButton.find('[data-testid="mt-icon__regular-checkmark"]').exists()).toBe(false);
            expect(copyButton.find('[data-testid="mt-icon__regular-copy"]').exists()).toBe(true);
        } finally {
            jest.useRealTimers();
        }
    });

    it('leaves the loading state and notifies when the update check fails', async () => {
        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        wrapper = await createWrapper({
            checkForUpdates: () => Promise.reject(new Error('update API unreachable')),
        });
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-settings-shopware-updates.notifications.checkFailed',
            }),
        );
    });

    it('disables the update methods and hides the extensions card when the compatibility check fails', async () => {
        const notificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

        wrapper = await createWrapper({
            checkLicense: () => Promise.resolve({ isValid: true }),
            extensionCompatibility: () => Promise.reject(new Error('store unreachable')),
        });
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(notificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                variant: 'error',
                message: 'sw-settings-shopware-updates.notifications.checkFailed',
            }),
        );

        const startUpdateButton = wrapper.get('.sw-settings-shopware-updates-wizard__start-update');

        expect(startUpdateButton.attributes('aria-disabled')).toBe('true');
        expect(wrapper.vm.updateButtonTooltipMessage).toBe('sw-settings-shopware-updates.notifications.checkFailed');
        expect(wrapper.find('.sw-settings-shopware-updates-extensions').exists()).toBe(false);
    });

    it('offers the web installer as a second update method behind a divider', async () => {
        const versionCard = wrapper.get('.sw-settings-shopware-updates-wizard__version-card');

        expect(versionCard.find('.sw-settings-shopware-updates-method-divider').exists()).toBe(true);

        const webInstaller = versionCard.get('.sw-settings-shopware-updates-web-installer');

        expect(webInstaller.text()).toContain('sw-settings-shopware-updates.versionCard.webInstallerTitle');
        expect(webInstaller.find('.sw-settings-shopware-updates-wizard__start-update').exists()).toBe(true);
    });

    it('copies the CLI command to the clipboard and shows a snackbar', async () => {
        const copySpy = jest.spyOn(Shopware.Utils.dom, 'copyStringToClipboard').mockResolvedValue();

        await wrapper.get('.sw-settings-shopware-updates-cli-method__copy-button').trigger('click');
        await flushPromises();

        expect(copySpy).toHaveBeenCalledWith('shopware-cli project upgrade');
        expect(mockSnackbar.addSnackbar).toHaveBeenCalledWith({
            message: 'global.sw-field.notification.notificationCopySuccessMessage',
            variant: 'success',
        });
    });

    it('shows extension deactivation options when incompatible extensions are installed', async () => {
        wrapper.vm.updateModalShown = true;
        await flushPromises();

        expect(wrapper.find('sw-radio-field-stub').exists()).toBe(false);

        wrapper.vm.extensions = [{ statusName: 'incompatible' }];
        await flushPromises();

        expect(wrapper.find('sw-radio-field-stub').exists()).toBe(true);
    });

    it('disables continue until a backup is confirmed', async () => {
        wrapper.vm.updateModalShown = true;
        await flushPromises();

        expect(wrapper.get('.sw-settings-shopware-updates-check__start-update-button').attributes('disabled')).toBeDefined();

        await wrapper.get('.sw-settings-shopware-updates-check__start-update-backup-checkbox input').setChecked(true);

        expect(
            wrapper.get('.sw-settings-shopware-updates-check__start-update-button').attributes('disabled'),
        ).toBeUndefined();
    });
});
