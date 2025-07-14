/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import shortcutPlugin from 'src/app/plugin/shortcut.plugin';
import 'src/app/component/form/sw-text-editor';
import 'src/app/component/form/sw-text-editor/sw-text-editor-toolbar';
import 'src/app/component/form/sw-text-editor/sw-text-editor-toolbar-button';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-colorpicker';
import 'src/app/component/form/sw-colorpicker-deprecated';
import 'src/app/component/form/sw-compact-colorpicker';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/base/sw-container';
import 'src/app/component/base/sw-button';

Shopware.Utils.debounce = function debounce(fn) {
    return function execFunction(...args) {
        fn.apply(this, args);
    };
};

const createWrapper = async (componentOverride = {}) => {
    const baseComponent = {
        name: 'base-component',
        template: '<div></div>',
        ...componentOverride,
    };

    const element = document.createElement('div');
    if (document.body) {
        document.body.appendChild(element);
    }

    return mount(baseComponent, {
        attachTo: element,
        global: {
            plugins: [shortcutPlugin],
        },
    });
};

function defineJsdomProperties() {
    // 'Implement' innerText in JSDOM: https://github.com/jsdom/jsdom/issues/1245
    Object.defineProperty(global.Element.prototype, 'innerText', {
        get() {
            return this.textContent;
        },
    });

    // 'Implement' isContentEditable in JSDOM: https://github.com/jsdom/jsdom/issues/1670
    Object.defineProperty(global.Element.prototype, 'isContentEditable', {
        get() {
            return this.getAttribute('contenteditable');
        },
    });
}

describe('app/plugins/shortcut.plugin', () => {
    let wrapper;

    it('should test with a Vue.js component', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('String: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: 'onSave',
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });
        await wrapper.trigger('keydown', {
            key: 'CTRL',
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with boolean active: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active: true,
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with boolean active: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active: false,
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function active: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return true;
                    },
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with function active: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return false;
                    },
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function active which access the vue instance: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return this.activeValue;
                    },
                    method: 'onSave',
                },
            },
            computed: {
                activeValue() {
                    return true;
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with function active which access the vue instance: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return this.activeValue;
                    },
                    method: 'onSave',
                },
            },
            computed: {
                activeValue() {
                    return false;
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function: function should be executed for each shortcut press', async () => {
        const onSaveMock = jest.fn();
        let shouldExecute = true;

        wrapper = await createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return shouldExecute;
                    },
                    method: 'onSave',
                },
            },
            methods: {
                onSave() {
                    onSaveMock();
                },
            },
        });

        // shortcut should be executed
        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        expect(onSaveMock).toHaveBeenCalledWith();

        // change value dynamically
        onSaveMock.mockReset();
        shouldExecute = false;

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's',
        });

        // shortcut should not be executed
        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Text editor component: should be blurred on save shortcut to react to content changes', async () => {
        defineJsdomProperties();
        const onSaveMock = jest.fn();
        let testString = 'foo';

        Shopware.Store.register({
            id: 'cmsPage',
        });

        Shopware.Component.register('base-component', {
            name: 'base-component',
            template: `
              <div>
                <sw-text-editor
                    :value="description"
                    @update:value="onUpdateModelValue"
                ></sw-text-editor>
              </div>
            `,
            shortcuts: {
                'SYSTEMKEY+S': 'onSave',
            },
            data() {
                return {
                    description: testString,
                };
            },
            methods: {
                onUpdateModelValue(value) {
                    this.description = value;
                },
                onSave() {
                    onSaveMock();
                    testString = this.description;
                },
            },
        });
        const element = document.createElement('div');
        if (document.body) {
            document.body.appendChild(element);
        }

        wrapper = mount(await Shopware.Component.build('base-component'), {
            attachTo: element,
            global: {
                plugins: [shortcutPlugin],
                stubs: {
                    'sw-text-editor': await wrapTestComponent('sw-text-editor'),
                    'sw-text-editor-toolbar': await wrapTestComponent('sw-text-editor-toolbar'),
                    'sw-text-editor-toolbar-button': await wrapTestComponent('sw-text-editor-toolbar-button'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),

                    'sw-field-error': true,
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-text-editor-table-toolbar': true,
                    'sw-code-editor': true,
                    'sw-text-editor-link-menu': true,
                    'sw-text-editor-toolbar-table-button': true,
                },
            },
        });

        await flushPromises();

        expect(onSaveMock).not.toHaveBeenCalled();
        expect(testString).toBe('foo');

        const contentEditor = await wrapper.find('.sw-text-editor__content-editor');
        contentEditor.element.blur = async () => {
            await contentEditor.trigger('blur');
        };

        // click in editable content
        await wrapper.trigger('click');

        // write something in the editor
        contentEditor.element.innerHTML = 'foobar';

        await contentEditor.trigger('input');
        await flushPromises();

        await contentEditor.trigger('keydown', {
            key: 's',
            ctrlKey: true,
        });

        expect(onSaveMock).toHaveBeenCalledWith();
        expect(testString).toBe('foobar');
    });

    it('should call the onEsc method when Escape key is pressed outside a modal', async () => {
        const onEscMock = jest.fn();

        wrapper = await createWrapper({
            shortcuts: {
                Escape: 'onEsc',
            },
            methods: {
                onEsc() {
                    onEscMock();
                },
            },
        });

        expect(onEscMock).not.toHaveBeenCalled();

        // Simulate Escape keydown event outside a modal
        await wrapper.trigger('keydown', {
            key: 'Escape',
        });

        expect(onEscMock).toHaveBeenCalledWith();
    });

    it('should NOT call the onEsc method when Escape key is pressed inside a modal', async () => {
        const onEscMock = jest.fn();

        // Create a modal element in the DOM
        const modal = document.createElement('div');
        modal.className = 'sw-modal';
        document.body.appendChild(modal);

        wrapper = await createWrapper({
            shortcuts: {
                Escape: 'onEsc',
            },
            methods: {
                onEsc() {
                    onEscMock();
                },
            },
        });

        expect(onEscMock).not.toHaveBeenCalled();

        // Simulate Escape keydown event with the target inside the modal
        const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
        Object.defineProperty(event, 'target', { value: modal, enumerable: true });

        document.dispatchEvent(event);

        expect(onEscMock).not.toHaveBeenCalled();

        // Clean up
        document.body.removeChild(modal);
    });

    it('should still trigger shortcuts after a component was unmounted', async () => {
        const onSaveMock = jest.fn();
        const onOtherSaveMock = jest.fn();

        // Mount first component with shortcut
        const wrapper1 = await createWrapper({
            name: 'component-1',
            shortcuts: {
                'SYSTEMKEY+S': 'onSave',
            },
            methods: {
                onSave: onSaveMock,
            },
        });

        // Mount second component with another shortcut
        const wrapper2 = await createWrapper({
            name: 'component-2',
            shortcuts: {
                'SYSTEMKEY+U': 'onOtherSave',
            },
            methods: {
                onOtherSave: onOtherSaveMock,
            },
        });

        // Unmount the first component
        wrapper1.unmount();
        await flushPromises();

        // Trigger shortcut of the second (still mounted) component
        await wrapper2.trigger('keydown', {
            key: 'u',
            ctrlKey: true,
        });

        // The first component's shortcut should not be called
        expect(onSaveMock).not.toHaveBeenCalled();
        // The second component's shortcut should be called
        expect(onOtherSaveMock).toHaveBeenCalled();

        wrapper2.unmount();
    });

    it('should not trigger shortcuts from unmounted components', async () => {
        const onSaveMock = jest.fn();

        // Mount component
        const wrapperComponent = await createWrapper({
            shortcuts: {
                'SYSTEMKEY+S': 'onSave',
            },
            methods: {
                onSave: onSaveMock,
            },
        });

        // Trigger shortcut, should work
        await wrapperComponent.trigger('keydown', {
            key: 's',
            ctrlKey: true,
        });
        expect(onSaveMock).toHaveBeenCalledTimes(1);

        // Unmount component
        wrapperComponent.unmount();
        await flushPromises();

        // Trigger shortcut again on the document, should not work anymore
        const event = new KeyboardEvent('keydown', { key: 's', ctrlKey: true, bubbles: true });
        document.dispatchEvent(event);
        await flushPromises();

        expect(onSaveMock).toHaveBeenCalledTimes(1);
    });
});
