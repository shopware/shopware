import { mount, shallowMount } from '@vue/test-utils';
import type { Component } from 'vue';
import detailComponent from './index';
import '../../store/experience-studio-workspace.store';
import type { ExperienceStudioWorkspaceStore } from '../../store/experience-studio-workspace.store';

/** @sw-package discovery */
describe('Experience Studio canvas save notifications', () => {
    let wrapper: ReturnType<typeof createWrapper>;
    let notifications: ReturnType<typeof mount>;
    let workspaceStore: ExperienceStudioWorkspaceStore;
    const notificationStore = () => Shopware.Store.get('notification');

    function createWrapper() {
        return shallowMount(
            {
                ...detailComponent,
                created() {},
                mounted() {},
                beforeUnmount() {},
            } as Component,
            {
                global: {
                    provide: {
                        repositoryFactory: { create: jest.fn() },
                        acl: { can: () => true },
                    },
                    mocks: { $route: { params: {} } },
                    stubs: {
                        'sw-page': { template: '<div><slot name="content" /></div>' },
                    },
                },
            },
        );
    }

    beforeEach(async () => {
        workspaceStore = Shopware.Store.get('experienceStudioWorkspace' as never) as ExperienceStudioWorkspaceStore;
        workspaceStore.$reset();
        notificationStore().$reset();
        wrapper = createWrapper();
        notifications = mount(await wrapTestComponent('sw-notifications', { sync: true }));
        await flushPromises();
    });

    afterEach(() => {
        wrapper.unmount();
        notifications.unmount();
        notificationStore().$reset();
        workspaceStore.$reset();
        jest.restoreAllMocks();
    });

    it('shows a floating error notification without adding an alert to the canvas layout', async () => {
        expect(Object.values(notificationStore().growlNotifications)).toHaveLength(0);

        workspaceStore.saveError = true;
        await flushPromises();

        expect(Object.values(notificationStore().growlNotifications)).toEqual([
            expect.objectContaining({
                variant: 'error',
                message: 'sw-experience-studio.workspace.manager.saveError',
                autoClose: false,
            }),
        ]);
        expect(wrapper.text()).not.toContain('sw-experience-studio.workspace.manager.saveError');
        expect(notifications.get('.sw-notifications__message').text()).toBe(
            'sw-experience-studio.workspace.manager.saveError',
        );
    });

    it('retries saving from the notification and closes it on success', async () => {
        const flush = jest.spyOn(workspaceStore, 'flush').mockImplementation(() => {
            workspaceStore.saveError = false;
            return Promise.resolve(true);
        });
        workspaceStore.saveError = true;
        await flushPromises();

        const retry = notifications.get('.sw-notifications__actions button');
        expect(retry.text()).toBe('sw-experience-studio.workspace.manager.retry');
        await retry.trigger('click');
        await flushPromises();

        expect(flush).toHaveBeenCalledTimes(1);
        expect(Object.values(notificationStore().growlNotifications)).toHaveLength(0);
    });

    it('keeps a retry notification available when saving fails again', async () => {
        const flush = jest.spyOn(workspaceStore, 'flush').mockResolvedValue(false);
        workspaceStore.saveError = true;
        await flushPromises();

        await notifications.get('.sw-notifications__actions button').trigger('click');
        await flushPromises();

        expect(flush).toHaveBeenCalledTimes(1);
        expect(Object.values(notificationStore().growlNotifications)).toHaveLength(1);
        expect(notifications.find('.sw-notifications__actions button').exists()).toBe(true);
    });

    it('notifies again after a later save failure', async () => {
        workspaceStore.saveError = true;
        await flushPromises();
        notificationStore().clearGrowlNotificationsForCurrentUser();
        workspaceStore.saveError = false;
        await flushPromises();

        workspaceStore.saveError = true;
        await flushPromises();

        expect(Object.values(notificationStore().growlNotifications)).toHaveLength(1);
    });
});
