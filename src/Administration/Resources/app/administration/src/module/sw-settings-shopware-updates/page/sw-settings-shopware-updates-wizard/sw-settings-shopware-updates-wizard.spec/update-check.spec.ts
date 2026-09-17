/**
 * @sw-package framework
 */
import createWrapper from './create-wrapper';

describe('Shopware update check failure and retry', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    afterEach(() => {
        wrapper.unmount();
    });

    it('shows a persistent error and no up-to-date state after a failed request', async () => {
        wrapper = await createWrapper({
            checkForUpdates: () => Promise.reject(new Error('update API unreachable')),
        });
        await flushPromises();

        const banner = wrapper.get('.sw-settings-shopware-updates-wizard__check-failed');
        expect(banner.attributes('variant')).toBe('critical');
        expect(banner.attributes('title')).toBe('sw-settings-shopware-updates.notifications.checkFailed');
        expect(banner.get('button').text()).toBe('sw-settings-shopware-updates.versionCard.retryCheck');
        expect(wrapper.get('.sw-settings-shopware-updates-version__status-badge').text()).toBe(
            'sw-settings-shopware-updates.versionCard.badgeCheckFailed',
        );
        expect(wrapper.find('.sw-settings-shopware-updates-up-to-date').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-shopware-updates-methods').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-shopware-updates-extensions').exists()).toBe(false);
    });

    it.each([
        null,
        '6.4.18.0',
    ])('retries the check and shows the successful result for version %s', async (version) => {
        let resolveCheck!: (response: { version: string | null }) => void;
        const checkForUpdates = jest
            .fn()
            .mockRejectedValueOnce(new Error('update API unreachable'))
            .mockImplementationOnce(
                () =>
                    new Promise((resolve) => {
                        resolveCheck = resolve;
                    }),
            );
        wrapper = await createWrapper({
            checkForUpdates,
            checkLicense: () => Promise.resolve({ isValid: true }),
        });
        await flushPromises();

        await wrapper.get('.sw-settings-shopware-updates-wizard__retry-check').trigger('click');
        expect(checkForUpdates).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.sw-settings-shopware-updates-version__status-badge').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-shopware-updates-up-to-date').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-shopware-updates-wizard__retry-check').exists()).toBe(false);

        resolveCheck({ version });
        await flushPromises();

        expect(wrapper.find('.sw-settings-shopware-updates-wizard__check-failed').exists()).toBe(false);
        expect(wrapper.get('.sw-settings-shopware-updates-version__status-badge').text()).toBe(
            version
                ? 'sw-settings-shopware-updates.versionCard.badgeUpdateAvailable'
                : 'sw-settings-shopware-updates.versionCard.badgeUpToDate',
        );
        expect(wrapper.find('.sw-settings-shopware-updates-up-to-date').exists()).toBe(version === null);
        expect(wrapper.find('.sw-settings-shopware-updates-extensions').exists()).toBe(version !== null);
    });

    it('keeps the error visible when the retry also fails', async () => {
        const checkForUpdates = jest.fn().mockRejectedValue(new Error('update API unreachable'));
        wrapper = await createWrapper({ checkForUpdates });
        await flushPromises();

        await wrapper.get('.sw-settings-shopware-updates-wizard__retry-check').trigger('click');
        await flushPromises();

        expect(checkForUpdates).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.sw-settings-shopware-updates-wizard__check-failed').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-shopware-updates-up-to-date').exists()).toBe(false);
    });

    it('recovers from a failed compatibility check and enables the web installer', async () => {
        const extensionCompatibility = jest
            .fn()
            .mockRejectedValueOnce(new Error('store unreachable'))
            .mockResolvedValueOnce([]);
        wrapper = await createWrapper({
            checkLicense: () => Promise.resolve({ isValid: true }),
            extensionCompatibility,
        });
        await flushPromises();

        expect(wrapper.find('.sw-settings-shopware-updates-wizard__check-failed').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-shopware-updates-version').exists()).toBe(true);
        expect(wrapper.get('.sw-settings-shopware-updates-wizard__start-update').attributes('aria-disabled')).toBe('true');

        await wrapper.get('.sw-settings-shopware-updates-wizard__retry-check').trigger('click');
        await flushPromises();

        expect(extensionCompatibility).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.sw-settings-shopware-updates-wizard__check-failed').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-shopware-updates-extensions').exists()).toBe(true);
        expect(wrapper.get('.sw-settings-shopware-updates-wizard__start-update').attributes('aria-disabled')).toBe('false');
    });
});
