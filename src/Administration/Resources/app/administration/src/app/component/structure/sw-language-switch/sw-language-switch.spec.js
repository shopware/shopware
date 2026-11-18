/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

describe('src/app/component/structure/sw-language-switch', () => {
    let wrapper = null;

    beforeEach(async () => {
        jest.restoreAllMocks();
        Shopware.Store.get('context').api.languageId = '123456789';

        wrapper = mount(await wrapTestComponent('sw-language-switch', { sync: true }), {
            global: {
                stubs: {
                    'sw-entity-single-select': true,
                    'sw-modal': {
                        template: `
                        <div class="sw-modal-stub">
                            <slot></slot>

                            <div class="modal-footer">
                                <slot name="modal-footer"></slot>
                            </div>
                        </div>
                    `,
                    },
                },
            },
        });
    });

    afterEach(() => {
        wrapper.unmount();
    });

    it('should change the language', async () => {
        Shopware.Store.get('context').api.languageId = '123';

        expect(Shopware.Store.get('context').api.languageId).toBe('123');

        wrapper.getComponent('sw-entity-single-select-stub').vm.$emit('update:value', '456');

        expect(Shopware.Store.get('context').api.languageId).toBe('456');
    });

    it('switches language through the shared event when there are no unsaved changes', () => {
        Shopware.Utils.EventBus.emit('on-change-language-clicked', '456');
        expect(Shopware.Context.api.languageId).toBe('456');
        expect(wrapper.emitted('on-change')).toEqual([['456']]);
    });

    it.each([true, false])('keeps the existing bus behavior with changeGlobalLanguage=%s', async (changeGlobalLanguage) => {
        const abortChangeFunction = jest.fn(() => true);
        await wrapper.setProps({ abortChangeFunction, changeGlobalLanguage });

        Shopware.Utils.EventBus.emit('on-change-language-clicked', '456');
        await flushPromises();

        expect(abortChangeFunction).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-modal-stub').exists()).toBe(false);
        expect(wrapper.emitted('on-change')).toEqual([['456']]);
        expect(Shopware.Context.api.languageId).toBe(changeGlobalLanguage ? '456' : '123456789');
    });

    it('keeps the current language when a bus request with unsaved changes is cancelled', async () => {
        const abortChangeFunction = jest.fn(() => true);
        await wrapper.setProps({ abortChangeFunction, checkChangesOnBusEvent: true });
        Shopware.Utils.EventBus.emit('on-change-language-clicked', '456');
        await flushPromises();
        expect(abortChangeFunction).toHaveBeenCalledWith({ oldLanguageId: '123456789', newLanguageId: '456' });
        expect(wrapper.find('.sw-modal-stub').exists()).toBe(true);
        expect(Shopware.Context.api.languageId).toBe('123456789');
        expect(wrapper.emitted('on-change')).toBeUndefined();

        await wrapper.get('#sw-language-switch-close-button').trigger('click');
        expect(wrapper.find('.sw-modal-stub').exists()).toBe(false);
        expect(Shopware.Context.api.languageId).toBe('123456789');
        expect(wrapper.emitted('on-change')).toBeUndefined();
    });

    it('switches after discarding changes requested through the bus without checking again', async () => {
        const abortChangeFunction = jest.fn(() => true);
        await wrapper.setProps({ abortChangeFunction, checkChangesOnBusEvent: true });
        Shopware.Utils.EventBus.emit('on-change-language-clicked', '456');
        await flushPromises();
        await wrapper.getComponent('#sw-language-switch-revert-changes-button').vm.$emit('click');
        expect(abortChangeFunction).toHaveBeenCalledTimes(1);
        expect(Shopware.Context.api.languageId).toBe('456');
        expect(wrapper.emitted('on-change')).toEqual([['456']]);
    });

    it('waits for a successful save before switching languages requested through the bus', async () => {
        let finishSave;
        const saveChangesFunction = jest.fn(
            () =>
                new Promise((resolve) => {
                    finishSave = resolve;
                }),
        );
        await wrapper.setProps({ abortChangeFunction: () => true, saveChangesFunction, checkChangesOnBusEvent: true });
        Shopware.Utils.EventBus.emit('on-change-language-clicked', '456');
        await flushPromises();
        wrapper.getComponent('#sw-language-switch-save-changes-button').vm.$emit('click');
        await flushPromises();
        expect(saveChangesFunction).toHaveBeenCalledTimes(1);
        expect(Shopware.Context.api.languageId).toBe('123456789');
        expect(wrapper.emitted('on-change')).toBeUndefined();

        finishSave();
        await flushPromises();
        expect(Shopware.Context.api.languageId).toBe('456');
        expect(wrapper.emitted('on-change')).toEqual([['456']]);
        expect(wrapper.find('.sw-modal-stub').exists()).toBe(false);
    });

    it('uses cached entity select options for active languages', () => {
        const languageSelect = wrapper.get('sw-entity-single-select-stub');

        expect(languageSelect.attributes('entity')).toBe('language');
        expect(languageSelect.attributes('cache-key')).toContain('shared-data');
        expect(languageSelect.attributes('cache-key')).toContain('active-languages');
        expect(languageSelect.attributes('cache-ttl')).toBe('300000');
    });

    it('should open a modal with a warning if abortChangesFunction is set', async () => {
        Shopware.Store.get('context').api.languageId = '123';

        await wrapper.setProps({
            abortChangeFunction: () => true,
        });
        await wrapper.getComponent('sw-entity-single-select-stub').vm.$emit('update:value', '456');

        const modal = wrapper.find('.sw-modal-stub');
        expect(modal.exists()).toBeTruthy();

        expect(wrapper.text()).toContain('sw-language-switch.messageModalUnsavedChanges');

        expect(Shopware.Store.get('context').api.languageId).toBe('123');
    });

    it('should revert the changes and set the new language', async () => {
        Shopware.Store.get('context').api.languageId = '123';
        const abortChangeMock = jest.fn(() => true);

        await wrapper.setProps({
            abortChangeFunction: abortChangeMock,
        });

        expect(abortChangeMock).not.toHaveBeenCalled();

        await wrapper.getComponent('sw-entity-single-select-stub').vm.$emit('update:value', '456');

        expect(Shopware.Store.get('context').api.languageId).toBe('123');

        expect(abortChangeMock).toHaveBeenCalledWith({
            newLanguageId: '456',
            oldLanguageId: '123456789',
        });

        const revertButton = wrapper.findComponent('#sw-language-switch-revert-changes-button');
        revertButton.vm.$emit('click');

        expect(Shopware.Store.get('context').api.languageId).toBe('456');
    });

    it('should save the changes and then set the new language', async () => {
        Shopware.Store.get('context').api.languageId = '123';
        const saveChangesMock = jest.fn(() => Promise.resolve());

        await wrapper.setProps({
            abortChangeFunction: () => true,
            saveChangesFunction: saveChangesMock,
        });

        await wrapper.getComponent('sw-entity-single-select-stub').vm.$emit('update:value', '456');

        expect(Shopware.Store.get('context').api.languageId).toBe('123');

        expect(saveChangesMock).not.toHaveBeenCalled();

        const revertButton = wrapper.findComponent('#sw-language-switch-save-changes-button');
        await revertButton.vm.$emit('click');

        expect(saveChangesMock).toHaveBeenCalled();

        expect(Shopware.Store.get('context').api.languageId).toBe('456');
    });

    it('should show a warning modal with save button enabled', async () => {
        Shopware.Store.get('context').api.languageId = '123';

        await wrapper.setProps({
            abortChangeFunction: () => true,
        });
        await wrapper.getComponent('sw-entity-single-select-stub').vm.$emit('update:value', '456');

        const saveButton = wrapper.find('#sw-language-switch-save-changes-button');
        expect(saveButton.attributes().disabled).toBeUndefined();
    });

    it('should show a warning modal with save button disabled', async () => {
        Shopware.Store.get('context').api.languageId = '123';

        await wrapper.setProps({
            abortChangeFunction: () => true,
            allowEdit: false,
        });
        await wrapper.getComponent('sw-entity-single-select-stub').vm.$emit('update:value', '456');

        const saveButton = wrapper.find('#sw-language-switch-save-changes-button');
        expect(saveButton.attributes('disabled')).toBeDefined();
    });
});
