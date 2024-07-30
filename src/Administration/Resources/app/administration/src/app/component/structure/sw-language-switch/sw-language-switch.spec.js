/**
 * @package admin
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';

describe('src/app/component/structure/sw-language-switch', () => {
    let wrapper = null;

    beforeEach(async () => {
        Shopware.State.commit('context/setApiLanguageId', '123456789');

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
                    'sw-button': true,
                },
            },
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should change the language', async () => {
        Shopware.State.commit('context/setApiLanguageId', '123');

        expect(Shopware.State.get('context').api.languageId).toBe('123');

        wrapper.vm.onInput('456');

        expect(Shopware.State.get('context').api.languageId).toBe('456');
    });

    it('should open a modal with a warning if abortChangesFunction is set', async () => {
        Shopware.State.commit('context/setApiLanguageId', '123');

        await wrapper.setProps({
            abortChangeFunction: () => true,
        });
        await wrapper.vm.onInput('456');

        const modal = wrapper.find('.sw-modal-stub');
        expect(modal.exists()).toBeTruthy();

        expect(wrapper.text()).toContain('sw-language-switch.messageModalUnsavedChanges');

        expect(Shopware.State.get('context').api.languageId).toBe('123');
    });

    it('should revert the changes and set the new language', async () => {
        Shopware.State.commit('context/setApiLanguageId', '123');
        const abortChangeMock = jest.fn(() => true);

        await wrapper.setProps({
            abortChangeFunction: abortChangeMock,
        });

        expect(abortChangeMock).not.toHaveBeenCalled();

        await wrapper.vm.onInput('456');

        expect(Shopware.State.get('context').api.languageId).toBe('123');

        expect(abortChangeMock).toHaveBeenCalledWith({
            newLanguageId: '456',
            oldLanguageId: '123456789',
        });

        const revertButton = wrapper.findComponent('#sw-language-switch-revert-changes-button');
        revertButton.vm.$emit('click');

        expect(Shopware.State.get('context').api.languageId).toBe('456');
    });

    it('should save the changes and then set the new language', async () => {
        Shopware.State.commit('context/setApiLanguageId', '123');
        const saveChangesMock = jest.fn(() => Promise.resolve());

        await wrapper.setProps({
            abortChangeFunction: () => true,
            saveChangesFunction: saveChangesMock,
        });

        await wrapper.vm.onInput('456');

        expect(Shopware.State.get('context').api.languageId).toBe('123');

        expect(saveChangesMock).not.toHaveBeenCalled();

        const revertButton = wrapper.findComponent('#sw-language-switch-save-changes-button');
        await revertButton.vm.$emit('click');

        expect(saveChangesMock).toHaveBeenCalled();

        expect(Shopware.State.get('context').api.languageId).toBe('456');
    });

    it('should show a warning modal with save button enabled', async () => {
        Shopware.State.commit('context/setApiLanguageId', '123');

        await wrapper.setProps({
            abortChangeFunction: () => true,
        });
        await wrapper.vm.onInput('456');

        const saveButton = wrapper.find('#sw-language-switch-save-changes-button');
        expect(saveButton.attributes().disabled).toBeUndefined();
    });

    it('should show a warning modal with save button disabled', async () => {
        Shopware.State.commit('context/setApiLanguageId', '123');

        await wrapper.setProps({
            abortChangeFunction: () => true,
            allowEdit: false,
        });
        await wrapper.vm.onInput('456');

        const saveButton = wrapper.find('#sw-language-switch-save-changes-button');
        expect(saveButton.attributes().disabled).toBe('true');
    });
});
