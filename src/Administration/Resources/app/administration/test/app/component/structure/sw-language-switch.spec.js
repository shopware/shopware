import { shallowMount, createLocalVue } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/app/component/structure/sw-language-switch';

describe('src/app/component/structure/sw-language-switch', () => {
    let wrapper = null;

    beforeAll(() => {});

    beforeEach(() => {
        const localVue = createLocalVue();
        localVue.use(Vuex);

        Shopware.State.commit('context/setApiLanguageId', '123456789');

        wrapper = shallowMount(Shopware.Component.build('sw-language-switch'), {
            localVue,
            stubs: {
                'sw-entity-single-select': true,
                'sw-modal': `
                    <div class="sw-modal-stub">
                        <slot></slot>

                        <div class="modal-footer">
                            <slot name="modal-footer"></slot>
                        </div>
                    </div>
                `,
                'sw-button': true
            },
            mocks: {
                $store: Shopware.State._store,
                $tc: v => v
            }
        });
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should change the language', () => {
        Shopware.State.commit('context/setApiLanguageId', '123');

        expect(Shopware.State.get('context').api.languageId).toBe('123');

        wrapper.vm.onInput('456');

        expect(Shopware.State.get('context').api.languageId).toBe('456');
    });

    it('should open a modal with a warning if abortChangesFunction is set', () => {
        Shopware.State.commit('context/setApiLanguageId', '123');

        wrapper.setProps({
            abortChangeFunction: () => true
        });
        wrapper.vm.onInput('456');

        const modal = wrapper.find('.sw-modal-stub');
        expect(modal.exists()).toBeTruthy();

        expect(wrapper.text()).toContain('sw-language-switch.messageModalUnsavedChanges');

        expect(Shopware.State.get('context').api.languageId).toBe('123');
    });

    it('should revert the changes and set the new language', async () => {
        Shopware.State.commit('context/setApiLanguageId', '123');
        const abortChangeMock = jest.fn(() => true);

        wrapper.setProps({
            abortChangeFunction: abortChangeMock
        });

        expect(abortChangeMock).not.toHaveBeenCalled();

        wrapper.vm.onInput('456');

        expect(Shopware.State.get('context').api.languageId).toBe('123');

        expect(abortChangeMock).toHaveBeenCalledWith({
            newLanguageId: '456',
            oldLanguageId: '123456789'
        });

        const revertButton = wrapper.find('#sw-language-switch-revert-changes-button');
        revertButton.vm.$emit('click');

        expect(Shopware.State.get('context').api.languageId).toBe('456');
    });

    it('should save the changes and then set the new language', async () => {
        Shopware.State.commit('context/setApiLanguageId', '123');
        const saveChangesMock = jest.fn(() => Promise.resolve());

        wrapper.setProps({
            abortChangeFunction: () => true,
            saveChangesFunction: saveChangesMock
        });

        wrapper.vm.onInput('456');

        expect(Shopware.State.get('context').api.languageId).toBe('123');

        expect(saveChangesMock).not.toHaveBeenCalled();

        const revertButton = wrapper.find('#sw-language-switch-save-changes-button');
        await revertButton.vm.$emit('click');

        expect(saveChangesMock).toHaveBeenCalled();

        expect(Shopware.State.get('context').api.languageId).toBe('456');
    });
});
