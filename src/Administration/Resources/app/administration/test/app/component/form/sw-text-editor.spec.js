import { shallowMount, createLocalVue } from '@vue/test-utils';
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

function createWrapper() {
    // set body for app
    document.body.innerHTML = '<div id="app"></div>';

    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-text-editor'), {
        attachTo: document.getElementById('app'),
        localVue,
        stubs: {
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
        mocks: {
            $tc: key => key,
            $sanitize: v => v,
            $device: {
                onResize: () => {},
                getViewportWidth: () => 1920
            }
        },
        provide: {
            validationService: {}
        }
    });
}


describe('src/app/component/form/sw-text-editor', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        // 'Implement' innerText in JSDOM: https://github.com/jsdom/jsdom/issues/1245
        Object.defineProperty(global.Element.prototype, 'innerText', {
            get() {
                return this.textContent;
            }
        });

        // implement execCommand mock
        document.execCommand = (command, ui, value) => {
            const range = document.getSelection().getRangeAt(0);
            if (!range) return;

            if (command === 'insertHTML') {
                const newNode = document.createElement('template');
                newNode.innerHTML = value.trim();

                range.deleteContents();
                range.insertNode(newNode.content.firstChild);
            }
        };
    });

    beforeEach(() => {});

    afterEach(() => {
        if (wrapper) { wrapper.destroy(); }
        document.getSelection().removeAllRanges();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should insert the link correctly', async () => {
        wrapper = await createWrapper();

        const contentEditor = wrapper.find('.sw-text-editor__content-editor');
        const buttonLink = wrapper.find('.sw-text-editor-toolbar-button__type-link');

        // click in editable content
        await wrapper.trigger('click');

        // write something in the editor
        contentEditor.element.innerHTML = '<p id="fooBarTest">Go to foo-bar</p>';
        const paragraph = document.getElementById('fooBarTest');

        await contentEditor.trigger('input');

        // set cursor range
        const range = document.createRange();
        range.setStart(paragraph.firstChild, 6);
        range.setEnd(paragraph.firstChild, 13);

        // check if range selects "foo-bar"
        expect(range.toString()).toBe('foo-bar');

        // check if nothing was selected
        expect(wrapper.vm.selection).toBe(null);

        // add range to selection
        const selection = document.getSelection();
        selection.addRange(range);

        // check if range and selection fits
        expect(range.toString()).toEqual('foo-bar');
        expect(selection.toString()).toEqual('foo-bar');
        expect(selection.rangeCount).toEqual(1);

        // add mouseup event to get selection
        document.dispatchEvent(new Event('mouseup'));
        await wrapper.vm.$nextTick();

        // check if selection was set
        expect(wrapper.vm.selection).toBe(selection);

        // click on button for link generation
        await buttonLink.find('.sw-text-editor-toolbar-button__icon').trigger('click');

        // set link
        await buttonLink.find('#sw-field--buttonConfig-value').setValue('https://www.foo-bar.com');
        await buttonLink.find('#sw-field--buttonConfig-value').trigger('change');

        // set new tab
        buttonLink.find('input[name="sw-field--buttonConfig-newTab"]').element.checked = true;
        await buttonLink.find('input[name="sw-field--buttonConfig-newTab"]').trigger('change');

        // insert link
        await buttonLink.find('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert').trigger('click');
        await wrapper.vm.$nextTick();

        // eslint-disable-next-line max-len
        const expectedValue = '<p id="fooBarTest">Go to <a target="_blank" href="https://www.foo-bar.com" rel="noopener">foo-bar</a></p>';

        // check if link was insert right in content editor
        expect(contentEditor.element.innerHTML).toEqual(expectedValue);

        // check if content value was emitted right
        const emittedValue = wrapper.emitted().input[0];
        expect(emittedValue[0]).toEqual(expectedValue);
    });
});
