/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import shortcutPlugin from 'src/app/plugin/shortcut.plugin';
import createWrapper from './create-wrapper';
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

describe('app/plugins/shortcut.plugin - blur on save', () => {
    let wrapper;

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

    it('Number field component: should be blurred on save shortcut to react to content changes', async () => {
        const onSaveMock = jest.fn();
        let savedPosition = 1;

        wrapper = await createWrapper({
            template: `
                <mt-number-field
                    v-model="position"
                    name="position"
                />
            `,
            shortcuts: {
                'SYSTEMKEY+S': 'onSave',
            },
            data() {
                return {
                    position: savedPosition,
                };
            },
            methods: {
                onSave() {
                    onSaveMock();
                    savedPosition = this.position;
                },
            },
        });

        await flushPromises();

        const numberInput = wrapper.get('input');
        numberInput.element.blur = () => {
            numberInput.element.dispatchEvent(new Event('change', { bubbles: true }));
        };

        await numberInput.setValue('2');

        expect(onSaveMock).not.toHaveBeenCalled();
        expect(savedPosition).toBe(1);

        await numberInput.trigger('keydown', {
            key: 's',
            ctrlKey: true,
        });

        expect(onSaveMock).toHaveBeenCalledWith();
        expect(savedPosition).toBe(2);
    });
});
