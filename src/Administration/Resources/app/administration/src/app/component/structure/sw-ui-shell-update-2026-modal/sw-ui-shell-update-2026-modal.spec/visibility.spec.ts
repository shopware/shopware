/**
 * @sw-package framework
 */

// @deprecated tag:v6.9.0 - Will be removed together with the one-time ui-shell-update-2026 announcement modal

import { type VueWrapper } from '@vue/test-utils';
import { NEW_NAVIGATION_RELEASE_DATE, UI_SHELL_UPDATE_2026_SEEN_CONFIG_KEY } from '../index';
import createWrapper, {
    AFTER_RELEASE,
    setCurrentUser,
    setIntendedAudience,
    setShopContext,
    setToday,
} from './create-wrapper';

describe('src/app/component/structure/sw-ui-shell-update-2026-modal - visibility', () => {
    let wrapper: VueWrapper | null = null;

    beforeEach(() => {
        setIntendedAudience();
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
            wrapper = null;
        }
    });

    it('shows the modal to a user of a shop that ran the old navigation', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(true);
        expect(wrapper.get('.mt-modal__title').text()).toBe('sw-ui-shell-update-2026-modal.title');
    });

    it('hides the modal up to the moment the navigation is released', async () => {
        setToday(new Date(new Date(NEW_NAVIGATION_RELEASE_DATE).getTime() - 1).toISOString());

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it('shows the modal from the moment the navigation is released', async () => {
        setToday(NEW_NAVIGATION_RELEASE_DATE);

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(true);
    });

    it('does not show the modal while the first run wizard is active', async () => {
        setShopContext({ firstRunWizard: true });

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it.each([
        [
            'the shop was migrated after the navigation was released',
            AFTER_RELEASE,
        ],
        [
            'the shop has never been migrated',
            null,
        ],
        [
            'the migration date cannot be read',
            'not-a-date',
        ],
    ])('does not show the modal when %s', async (_case, firstMigrationDate) => {
        setShopContext({ firstMigrationDate });

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it.each([
        [
            'the account was created after the navigation was released',
            AFTER_RELEASE,
        ],
        [
            'the creation date cannot be read',
            'not-a-date',
        ],
        [
            'the creation date is missing',
            null,
        ],
    ])('does not show the modal to a brand new user of an old shop when %s', async (_case, createdAt) => {
        setCurrentUser(createdAt);

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it('does not show the modal again once the user has seen it', async () => {
        (Shopware.Service('userConfigService').search as jest.Mock).mockResolvedValue({
            data: { [UI_SHELL_UPDATE_2026_SEEN_CONFIG_KEY]: { seen: true } },
        });

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
    });

    it('shows the modal while the flag says the user has not seen it', async () => {
        (Shopware.Service('userConfigService').search as jest.Mock).mockResolvedValue({
            data: { [UI_SHELL_UPDATE_2026_SEEN_CONFIG_KEY]: { seen: false } },
        });

        wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(true);
    });

    it('records on the user account that the modal has been seen', async () => {
        const upsert = jest.spyOn(Shopware.Service('userConfigService'), 'upsert');

        wrapper = await createWrapper();
        await flushPromises();

        expect(upsert).not.toHaveBeenCalled();

        await wrapper.get('.mt-modal__close-button').trigger('click');
        await flushPromises();

        expect(upsert).toHaveBeenCalledWith({ [UI_SHELL_UPDATE_2026_SEEN_CONFIG_KEY]: { seen: true } });
        expect(upsert).toHaveBeenCalledTimes(1);
    });

    it('records the flag exactly once when the modal is finished rather than closed', async () => {
        const upsert = jest.spyOn(Shopware.Service('userConfigService'), 'upsert');

        wrapper = await createWrapper();
        await flushPromises();

        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await wrapper.get('.sw-ui-shell-update-2026-modal__footer-right button').trigger('click');
        await flushPromises();

        // Closing reaches recordSeen twice: from onFinish and from MtModalRoot reporting the same close.
        expect(upsert).toHaveBeenCalledWith({ [UI_SHELL_UPDATE_2026_SEEN_CONFIG_KEY]: { seen: true } });
        expect(upsert).toHaveBeenCalledTimes(1);
    });

    it('notifies the user when the flag cannot be recorded', async () => {
        (Shopware.Service('userConfigService').upsert as jest.Mock).mockRejectedValue(new Error('nope'));

        wrapper = await createWrapper();
        await flushPromises();

        const createNotificationError = jest.spyOn(
            wrapper.vm as unknown as { createNotificationError: (config: unknown) => void },
            'createNotificationError',
        );

        await wrapper.get('.mt-modal__close-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.mt-modal').exists()).toBe(false);
        expect(createNotificationError).toHaveBeenCalledWith({
            message: 'sw-ui-shell-update-2026-modal.seenSaveError',
        });
    });
});
