/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import SwCodeEditor from 'src/app/asyncComponent/form/sw-code-editor';

Shopware.Component.register('sw-code-editor', SwCodeEditor);

const vulnerableInput = '<script>alert("Bämmmmm");</script>';
const sanitizedInput = 'User input cleared';

let serviceShouldWork = true;

const userInputSanitizeService = {
    sanitizeInput: jest.fn(() => new Promise((resolve, reject) => {
        if (serviceShouldWork) {
            resolve({
                preview: sanitizedInput,
            });
        } else {
            reject(new Error(`this serviceShouldWork is ${serviceShouldWork ? 'true' : 'false'}`));
        }
    })),
};

async function createWrapper(options = {}) {
    return shallowMount(await Shopware.Component.build('sw-code-editor'), {
        provide: { userInputSanitizeService },
        stubs: {
            'sw-circle-icon': { template: '<i/>' },
        },
        ...options,
    });
}

describe('asyncComponents/form/sw-code-editor', () => {
    Shopware.Service().register('userInputSanitizeService', () => userInputSanitizeService);
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should not be read only when enabled', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.aceConfig.readOnly).toBe(false);
    });

    it('should be read only when disabled', async () => {
        const wrapper = await createWrapper({
            propsData: {
                disabled: true,
            },
        });

        expect(wrapper.vm.aceConfig.readOnly).toBe(true);
    });

    it('should sanitize content when `sanitize-input` attibute is true', async () => {
        const wrapper = await createWrapper({
            propsData: {
                sanitizeInput: true,
            },
        });

        await wrapper.vm.editor.setValue(vulnerableInput, 1);
        await wrapper.vm.sanitizeEditorInput(vulnerableInput);
        expect(wrapper.vm.editor.getValue()).toBe(sanitizedInput);

        // tell `userInputSanitizeService` to reject
        serviceShouldWork = false;
        await wrapper.vm.editor.setValue(vulnerableInput, 1);
        await wrapper.vm.sanitizeEditorInput(vulnerableInput);
        expect(wrapper.vm.editor.getValue()).toBe(vulnerableInput);

        // now switch `sanitize-input` off
        await wrapper.setProps({
            sanitizeInput: false,
        });

        await wrapper.vm.editor.setValue(vulnerableInput, 1);
        await wrapper.vm.sanitizeEditorInput(vulnerableInput);
        expect(wrapper.vm.editor.getValue()).toBe(vulnerableInput);
    });
});
