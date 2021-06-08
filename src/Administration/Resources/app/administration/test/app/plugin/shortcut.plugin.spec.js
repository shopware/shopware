import { mount, createLocalVue } from '@vue/test-utils';
import shortcutPlugin from 'src/app/plugin/shortcut.plugin';
import 'src/app/component/form/sw-text-editor';
import 'src/app/component/form/sw-text-editor/sw-text-editor-toolbar';
import 'src/app/component/form/sw-text-editor/sw-text-editor-toolbar-button';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-colorpicker';
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

const localVue = createLocalVue();
localVue.use(shortcutPlugin);

const createWrapper = (componentOverride) => {
    const baseComponent = {
        name: 'base-component',
        template: '<div></div>',
        ...componentOverride
    };

    const element = document.createElement('div');
    if (document.body) {
        document.body.appendChild(element);
    }

    return mount(baseComponent, {
        attachTo: element,
        localVue
    });
};

function defineJsdomProperties() {
    // 'Implement' innerText in JSDOM: https://github.com/jsdom/jsdom/issues/1245
    Object.defineProperty(global.Element.prototype, 'innerText', {
        get() {
            return this.textContent;
        }
    });

    // 'Implement' isContentEditable in JSDOM: https://github.com/jsdom/jsdom/issues/1670
    Object.defineProperty(global.Element.prototype, 'isContentEditable', {
        get() {
            return this.getAttribute('contenteditable');
        }
    });
}

describe('app/plugins/shortcut.plugin', () => {
    let wrapper;

    afterEach(() => {
        wrapper.destroy();
    });

    it('should test with a Vue.js component', async () => {
        wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('String: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: 'onSave'
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });
        await wrapper.trigger('keydown', {
            key: 'CTRL'
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with boolean active: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active: true,
                    method: 'onSave'
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with boolean active: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active: false,
                    method: 'onSave'
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function active: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return true;
                    },
                    method: 'onSave'
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with function active: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return false;
                    },
                    method: 'onSave'
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function active which access the vue instance: should call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return this.activeValue;
                    },
                    method: 'onSave'
                }
            },
            computed: {
                activeValue() {
                    return true;
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).toHaveBeenCalledWith();
    });

    it('Object with function active which access the vue instance: should NOT call the onSave method', async () => {
        const onSaveMock = jest.fn();

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return this.activeValue;
                    },
                    method: 'onSave'
                }
            },
            computed: {
                activeValue() {
                    return false;
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Object with function: function should be executed for each shortcut press', async () => {
        const onSaveMock = jest.fn();
        let shouldExecute = true;

        wrapper = createWrapper({
            shortcuts: {
                s: {
                    active() {
                        return shouldExecute;
                    },
                    method: 'onSave'
                }
            },
            methods: {
                onSave() {
                    onSaveMock();
                }
            }
        });

        // shortcut should be executed
        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        expect(onSaveMock).toHaveBeenCalledWith();

        // change value dynamically
        onSaveMock.mockReset();
        shouldExecute = false;

        expect(onSaveMock).not.toHaveBeenCalled();

        await wrapper.trigger('keydown', {
            key: 's'
        });

        // shortcut should not be executed
        expect(onSaveMock).not.toHaveBeenCalledWith();
    });

    it('Text editor component: should be blurred on save shortcut to react to content changes', async () => {
        defineJsdomProperties();
        const onSaveMock = jest.fn();
        let testString = 'foo';

        Shopware.Component.register('base-component', {
            name: 'base-component',
            template: '<div><sw-text-editor v-model="description"></sw-text-editor></div>',
            shortcuts: {
                'SYSTEMKEY+S': 'onSave'
            },
            data() {
                return {
                    description: testString
                };
            },
            methods: {
                onSave() {
                    onSaveMock();
                    testString = this.description;
                }
            }
        });
        const element = document.createElement('div');
        if (document.body) {
            document.body.appendChild(element);
        }

        wrapper = await mount(Shopware.Component.build('base-component'), {
            attachTo: element,
            localVue,
            stubs: {
                'sw-text-editor': Shopware.Component.build('sw-text-editor'),
                'sw-text-editor-toolbar': Shopware.Component.build('sw-text-editor-toolbar'),
                'sw-text-editor-toolbar-button': Shopware.Component.build('sw-text-editor-toolbar-button'),
                'sw-icon': { template: '<div class="sw-icon"></div>' },
                'sw-field': Shopware.Component.build('sw-field'),
                'sw-text-field': Shopware.Component.build('sw-text-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
                'sw-switch-field': Shopware.Component.build('sw-switch-field'),
                'sw-field-error': true,
                'sw-compact-colorpicker': Shopware.Component.build('sw-compact-colorpicker'),
                'sw-colorpicker': Shopware.Component.build('sw-colorpicker'),
                'sw-container': Shopware.Component.build('sw-container'),
                'sw-button': Shopware.Component.build('sw-button')
            },
            shouldProxy: true
        });

        expect(onSaveMock).not.toHaveBeenCalled();
        expect(testString).toBe('foo');

        const contentEditor = wrapper.find('.sw-text-editor__content-editor');
        contentEditor.element.blur = () => {
            contentEditor.trigger('blur');
        };

        // click in editable content
        await wrapper.trigger('click');

        // write something in the editor
        contentEditor.element.innerHTML = 'foobar';

        await contentEditor.trigger('input');

        await contentEditor.trigger('keydown', {
            key: 's',
            ctrlKey: true
        });

        expect(onSaveMock).toHaveBeenCalledWith();
        expect(testString).toBe('foobar');
    });
});
