/**
 * @package admin
 * @group disabledCompat
 */

import { mount } from '@vue/test-utils';

async function createWrapper(allowInlineDataMapping = true) {
    // set body for app
    document.body.innerHTML = '<div id="app"></div>';

    return mount(await wrapTestComponent('sw-text-editor', { sync: true }), {
        attachTo: document.getElementById('app'),
        props: {
            allowInlineDataMapping,
        },
        global: {
            stubs: {
                'sw-text-editor-toolbar-button': await wrapTestComponent('sw-text-editor-toolbar-button'),
                'sw-text-editor-link-menu': await wrapTestComponent('sw-text-editor-link-menu'),
                'sw-compact-colorpicker': await wrapTestComponent('sw-compact-colorpicker'),
                'sw-text-editor-toolbar': await wrapTestComponent('sw-text-editor-toolbar'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-colorpicker': await wrapTestComponent('sw-colorpicker'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-media-field': await wrapTestComponent('sw-media-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-code-editor': { template: '<div id="sw-code-editor"></div>' },
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-icon': { template: '<div class="sw-icon"></div>' },
                'sw-select-field': true,
                'sw-field-error': true,
                'sw-text-editor-table-toolbar': true,
                'sw-text-editor-toolbar-table-button': true,
                'sw-email-field': true,
                'sw-entity-single-select': true,
                'sw-category-tree-field': true,
                'mt-button': true,
                'router-link': true,
                'sw-loader': true,
                'mt-text-field': true,
                'sw-field-copyable': true,
                'mt-switch': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
            provide: {
                validationService: {},
            },
        },
    });
}

async function addTextToEditor(wrapper, content) {
    const contentEditor = wrapper.find('.sw-text-editor__content-editor');

    // click in editable content
    await wrapper.trigger('click');

    // write something in the editor
    contentEditor.element.innerHTML = content;

    await contentEditor.trigger('input');
}

async function addAndCheckSelection(wrapper, element, start, end, text) {
    // set cursor range
    const range = document.createRange();
    range.setStart(element.firstChild, start);
    range.setEnd(element.firstChild, end);

    // check if range selects "foo-bar"
    expect(range.toString()).toBe(text);

    // check if nothing was selected
    expect(wrapper.vm.selection).toBeNull();

    // add range to selection
    const selection = document.getSelection();
    selection.addRange(range);

    // check if range and selection fits
    expect(range.toString()).toEqual(text);
    expect(selection.toString()).toEqual(text);
    expect(selection.rangeCount).toBe(1);

    // add mouseup event to get selection
    document.dispatchEvent(new Event('mouseup'));
    await wrapper.vm.$nextTick();

    // check if selection was set
    expect(wrapper.vm.selection).toBe(selection);
}

async function clearSelection(wrapper) {
    const selection = document.getSelection();
    selection.removeAllRanges();
    document.dispatchEvent(new Event('mouseup'));
    wrapper.vm.selection = null;
    await wrapper.vm.$nextTick();
}

describe('src/app/component/form/sw-text-editor', () => {
    /** @type Wrapper */
    let wrapper;

    beforeAll(() => {
        Shopware.Store.register({
            id: 'cmsPageState',
            state: () => ({
                currentMappingTypes: {
                    string: [
                        'category.type',
                    ],
                },
            }),
        });

        // 'Implement' innerText in JSDOM: https://github.com/jsdom/jsdom/issues/1245
        Object.defineProperty(global.Element.prototype, 'innerText', {
            get() {
                return this.textContent;
            },
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

            if (command === 'insertText') {
                const newTextNode = document.createTextNode(value.trim());

                range.deleteContents();
                range.insertNode(newTextNode);
            }
        };
    });

    afterEach(() => {
        document.getSelection().removeAllRanges();
    });

    it('should be a Vue.js component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should toggle placeholder', async () => {
        wrapper = await createWrapper();
        const placeholder = 'Enter description...';
        await wrapper.setProps({ placeholder: placeholder });

        // replace placeholder with value
        const editorPlaceholder = wrapper.find('.sw-text-editor__content .sw-text-editor__content-placeholder');
        expect(editorPlaceholder.element.innerText).toEqual(placeholder);

        const content = wrapper.find('.sw-text-editor__content .sw-text-editor__content-editor');
        const expectedValue = 'I am not the Placeholder';
        await wrapper.setProps({ value: expectedValue });

        expect(content.element.innerText).toEqual(expectedValue);
        expect(wrapper.find('.sw-text-editor__content .sw-text-editor__content-placeholder').exists()).toBeFalsy();

        // replace value with placeholder
        await wrapper.setProps({ value: null });
        expect(wrapper.find('.sw-text-editor__content .sw-text-editor__content-placeholder').exists()).toBeTruthy();
        expect(editorPlaceholder.element.innerText).toEqual(placeholder);
        expect(content.element.innerText).toBe('');
    });

    it('should update the placeholderVisible prop in the code editor mode', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const placeholder = 'Enter description...';
        await wrapper.setProps({ placeholder: placeholder });

        // check the editor placeholder
        expect(wrapper.find('.sw-text-editor__content-placeholder').element.innerText).toEqual(placeholder);
        expect(wrapper.vm.isCodeEdit).toBe(false);

        // switch to code editor mode
        await wrapper.find('.sw-icon[name="regular-code-xs"]').trigger('click');

        await wrapper.vm.$nextTick();
        expect(wrapper.vm.isCodeEdit).toBe(true);

        // The placeholder should disappear, but the flag is still set
        expect(wrapper.find('.sw-text-editor__content-placeholder').exists()).toBe(false);
        expect(wrapper.vm.placeholderVisible).toBe(true);


        // input something and expect the placeholderVisible flag to be unset
        wrapper.findComponent('#sw-code-editor').vm.$emit('blur', 'something');
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.placeholderVisible).toBe(false);

        // switch to text editor mode and make sure that the placeholder is not displayed
        await wrapper.find('.sw-icon[name="regular-code-xs"]').trigger('click');

        await wrapper.vm.$nextTick();
        expect(wrapper.vm.isCodeEdit).toBe(false);
        expect(wrapper.find('.sw-text-editor__content-placeholder').exists()).toBe(false);
    });

    it('should insert the link correctly', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const contentEditor = wrapper.find('.sw-text-editor__content-editor');
        const buttonLink = wrapper.find('.sw-text-editor-toolbar-button__type-link');

        await addTextToEditor(wrapper, '<p id="fooBarTest">Go to foo-bar</p>');

        const paragraph = document.getElementById('fooBarTest');
        await addAndCheckSelection(wrapper, paragraph, 6, 13, 'foo-bar');

        // click on button for link generation
        await buttonLink.find('.sw-text-editor-toolbar-button__icon').trigger('click');
        await flushPromises();

        // set link
        await buttonLink.find('#sw-field--linkTarget').setValue('https://www.foo-bar.com');
        await buttonLink.find('#sw-field--linkTarget').trigger('change');

        // set new tab
        buttonLink.find('.sw-text-editor-toolbar-button__link-menu-new-tab input').element.checked = true;
        await buttonLink.find('.sw-text-editor-toolbar-button__link-menu-new-tab input').trigger('change');

        // insert link
        await buttonLink.find('.sw-text-editor-toolbar-button__link-menu-buttons-button-insert').trigger('click');
        await wrapper.vm.$nextTick();

        const expectedValue =
            '<p id="fooBarTest">Go to <a target="_blank" href="https://www.foo-bar.com" rel="noopener">foo-bar</a></p>';

        // check if link was inserted correctly into content editor
        expect(contentEditor.element.innerHTML).toEqual(expectedValue);

        // check if content value was emitted right
        const emittedValue = wrapper.emitted('update:value')[1];
        expect(emittedValue[0]).toEqual(expectedValue);
    });

    const buttonVariantsDataProvider = [{
        buttonVariant: 'none',
        resultClasses: '',
    }, {
        buttonVariant: 'primary',
        resultClasses: 'btn btn-primary',
    }, {
        buttonVariant: 'secondary',
        resultClasses: 'btn btn-secondary',
    }, {
        buttonVariant: 'primary-sm',
        resultClasses: 'btn btn-primary btn-sm',
    }, {
        buttonVariant: 'secondary-sm',
        resultClasses: 'btn btn-secondary btn-sm',
    }];

    buttonVariantsDataProvider.forEach(({ buttonVariant, resultClasses }) => {
        it(`should always render correct links as correct button types (buttonVariant: ${buttonVariant})`, async () => {
            wrapper = await createWrapper();

            // set initial content
            const contentEditor = wrapper.find('.sw-text-editor__content-editor');
            await addTextToEditor(wrapper, '<p id="fooBarTest">foo-bar</p>');

            // select content
            const paragraph = document.getElementById('fooBarTest');
            await addAndCheckSelection(wrapper, paragraph, 0, 7, 'foo-bar');

            // prepare expected result
            const displayAsButton = buttonVariant !== 'none';
            const link = {
                value: 'https://www.foo-bar.com',
                target: '_blank',
                classes: displayAsButton ? ` class="${resultClasses}"` : '',
            };
            const linkParameters = `target="${link.target}" href="${link.value}" rel="noopener"`;
            const expectedValue = `<p id="fooBarTest"><a ${linkParameters}${link.classes}>foo-bar</a></p>`;

            // generate link
            wrapper.vm.onSetLink(link.value, link.target, displayAsButton, buttonVariant);
            await wrapper.vm.$nextTick();

            // check if link was inserted correctly into content editor
            expect(contentEditor.element.innerHTML).toEqual(expectedValue);

            // check if content value was emitted right
            const emittedValue = wrapper.emitted('update:value')[1];
            expect(emittedValue[0]).toEqual(expectedValue);
        });
    });

    it('should handle inserting inline mapping', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const contentEditor = wrapper.find('.sw-text-editor__content-editor');

        await addTextToEditor(wrapper, '<p id="text-editor-content">some random text</p>');
        const paragraph = document.getElementById('text-editor-content');

        await addAndCheckSelection(wrapper, paragraph, 12, 16, 'text');

        // eslint-disable-next-line max-len
        const inlineMappingButton = wrapper.find('.sw-text-editor-toolbar-button__type-data-mapping .sw-text-editor-toolbar-button__icon');
        await inlineMappingButton.trigger('click');
        await flushPromises();

        // insert inline data mapping
        await wrapper.find('.sw-text-editor-toolbar-button__children :first-child > div').trigger('click');
        await flushPromises();

        // check if newly edited content is correct
        const expectedTextContent = '<p id="text-editor-content">some random {{ category.type }}</p>';
        expect(contentEditor.element.innerHTML).toBe(expectedTextContent);

        // check emitted events
        const event = wrapper.emitted('update:value')[1];
        expect(event[0]).toBe(expectedTextContent);
    });

    it('should return true if selection contains one or two opening brackets', async () => {
        wrapper = await createWrapper();

        const containsOneBracket = wrapper.vm.containsStartBracket('{');
        expect(containsOneBracket).toBe(true);

        const containsTwoBrackets = wrapper.vm.containsStartBracket('{{');
        expect(containsTwoBrackets).toBe(true);
    });

    it('should return false if selection contains no opening brackets', async () => {
        wrapper = await createWrapper();

        const containsStartBracket = wrapper.vm.containsStartBracket('no start bracket');
        expect(containsStartBracket).toBe(false);
    });

    it('should return true if selection contains one or two closing brackets', async () => {
        wrapper = await createWrapper();

        const containsOneBracket = wrapper.vm.containsEndBracket('}');
        expect(containsOneBracket).toBe(true);

        const containsTwoBrackets = wrapper.vm.containsEndBracket('}}');
        expect(containsTwoBrackets).toBe(true);
    });

    it('should return false if selection contains no closing brackets', async () => {
        wrapper = await createWrapper();

        const containsEndBracket = wrapper.vm.containsStartBracket('no start bracket');
        expect(containsEndBracket).toBe(false);
    });

    it('should return true if selection is inside inline mapping', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<p id="paragraph">{{ category.name }}</p>');

        const paragraph = document.getElementById('paragraph');
        await addAndCheckSelection(wrapper, paragraph, 3, 11, 'category');

        const isInsideInlineMapping = wrapper.vm.isInsideInlineMapping();
        expect(isInsideInlineMapping).toBe(true);
    });

    it('should return true if selection is inside inline mapping with mapping around', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<p id="paragraph">Before {{ test }} {{ category.name }} {{ example }}</p>');

        const paragraph = document.getElementById('paragraph');
        await addAndCheckSelection(wrapper, paragraph, 21, 29, 'category');

        const isInsideInlineMapping = wrapper.vm.isInsideInlineMapping();
        expect(isInsideInlineMapping).toBe(true);
    });

    it('should return false if selection is not inside inline mapping with mapping around', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<p id="paragraph">Some text before {{ test }} category.name }} {{ example }}</p>');

        const paragraph = document.getElementById('paragraph');
        await addAndCheckSelection(wrapper, paragraph, 28, 36, 'category');

        const isInsideInlineMapping = wrapper.vm.isInsideInlineMapping();
        expect(isInsideInlineMapping).toBe(false);
    });

    it('should return false if selection is not inside inline mapping', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<p id="paragraph">not inside inline mapping</p>');

        const paragraph = document.getElementById('paragraph');
        await addAndCheckSelection(wrapper, paragraph, 4, 10, 'inside');

        const isInsideInlineMapping = wrapper.vm.isInsideInlineMapping();
        expect(isInsideInlineMapping).toBe(false);
    });

    it('should return false if selection is not inside inline mapping with mappings around', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<p id="paragraph">{{ example }} not inside mapping {{ example }}</p>');

        const paragraph = document.getElementById('paragraph');
        await addAndCheckSelection(wrapper, paragraph, 18, 24, 'inside');

        const isInsideInlineMapping = wrapper.vm.isInsideInlineMapping();
        expect(isInsideInlineMapping).toBe(false);
    });

    it('should expand selection to nearest closing bracket', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<p id="paragraph">{{ category.name }}</p>');

        const paragraph = document.getElementById('paragraph');
        await addAndCheckSelection(wrapper, paragraph, 0, 5, '{{ ca');

        wrapper.vm.expandSelectionToNearestEndBracket();

        const expandedSelection = document.getSelection();
        expect(expandedSelection.toString()).toBe('{{ category.name }}');
    });

    it('should expand selection to nearest opening bracket', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<p id="paragraph">{{ category.name }}</p>');

        const paragraph = document.getElementById('paragraph');
        await addAndCheckSelection(wrapper, paragraph, 15, 19, 'e }}');

        wrapper.vm.expandSelectionToNearestStartBracket();

        const expandedSelection = document.getSelection();
        expect(expandedSelection.toString()).toBe('{{ category.name }}');
    });

    it('should set the selection correctly when using the setSelection method', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<p id="paragraph">random text</p>');
        const paragraph = document.getElementById('paragraph');

        // add empty selection
        wrapper.vm.selection = document.getSelection();

        // check that nothing is selected
        const emptySelection = document.getSelection().toString();
        expect(emptySelection).toBe('');

        // add selection
        wrapper.vm.setSelection(paragraph.firstChild, paragraph.firstChild, 3, 6);

        const newSelection = document.getSelection().toString();
        expect(newSelection).toBe('dom');
    });

    it('should expand selection one to the left if only one opening bracket is selected', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<p id="paragraph">{{ category.name }}</p>');
        const paragraph = document.getElementById('paragraph');

        await addAndCheckSelection(wrapper, paragraph, 1, 4, '{ c');

        wrapper.vm.expandSelectionToNearestEndBracket();

        const newSelection = document.getSelection().toString();
        expect(newSelection).toBe('{{ category.name }}');
    });

    it('should expand selection one to the right if only one closing bracket is selected', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<p id="paragraph">{{ category.name }}</p>');
        const paragraph = document.getElementById('paragraph');

        await addAndCheckSelection(wrapper, paragraph, 15, 18, 'e }');

        wrapper.vm.expandSelectionToNearestStartBracket();

        const newSelection = document.getSelection().toString();
        expect(newSelection).toBe('{{ category.name }}');
    });

    it('should not show the inline mapping button when prop does not allow it to', async () => {
        wrapper = await createWrapper(false);
        const inlineMappingButton = wrapper.find('.sw-text-editor-toolbar-button__type-data-mapping');

        expect(inlineMappingButton.exists()).toBe(false);
    });

    it('should show the link url when you select a text block with a link', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, `
            <p id="paragraphWithoutLink">No Link</p>

            <p id="paragraphWithLink">
                <a id="linkText" href="http://shopware.com" target="_self">Shopware</a>
            </p>
        `);

        // select "Shopware"
        const linkText = document.getElementById('linkText');
        await addAndCheckSelection(wrapper, linkText, 0, 8, 'Shopware');
        document.dispatchEvent(new Event('mouseup'));

        // click on link button
        const linkButtonIcon = wrapper.find('.sw-text-editor-toolbar-button__type-link .sw-text-editor-toolbar-button__icon');
        await linkButtonIcon.trigger('click');
        await flushPromises();

        // link menu should be opened
        const linkMenu = wrapper.find('.sw-text-editor-toolbar-button__link-menu');
        expect(linkMenu.exists()).toBe(true);

        // input field should contain the correct url value
        const linkInput = linkMenu.find('#sw-field--linkTarget');
        expect(linkInput.exists()).toBe(true);
        expect(linkInput.element.value).toBe('http://shopware.com');

        // switch field should contain correct newTab value
        const newTabSwitch = wrapper.find('.sw-text-editor-toolbar-button__link-menu-new-tab input');
        expect(newTabSwitch.element.checked).toBe(false);
    });

    it('should show the link url with newTab active when you select a text block with a link', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, `
            <p id="paragraphWithoutLink">No Link</p>

            <p id="paragraphWithLink">
                <a id="linkText" href="http://shopware.com" target="_blank">Shopware</a>
            </p>
        `);

        // select "Shopware"
        const linkText = document.getElementById('linkText');
        await addAndCheckSelection(wrapper, linkText, 0, 8, 'Shopware');
        document.dispatchEvent(new Event('mouseup'));

        // click on link button
        const linkButtonIcon = wrapper.find('.sw-text-editor-toolbar-button__type-link .sw-text-editor-toolbar-button__icon');
        await linkButtonIcon.trigger('click');
        await flushPromises();

        // link menu should be opened
        const linkMenu = wrapper.find('.sw-text-editor-toolbar-button__link-menu');
        expect(linkMenu.exists()).toBe(true);

        // input field should contain the correct url value
        const linkInput = linkMenu.find('#sw-field--linkTarget');
        expect(linkInput.exists()).toBe(true);
        expect(linkInput.element.value).toBe('http://shopware.com');

        // switch field should contain correct newTab value
        const newTabSwitch = wrapper.find('.sw-text-editor-toolbar-button__link-menu-new-tab input');
        expect(newTabSwitch.element.checked).toBe(true);
    });

    it('should show no link url when you select a text block without a link', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, `
            <p id="paragraphWithoutLink">No link</p>

            <p id="paragraphWithLink">
                <a id="linkText" href="http://shopware.com" target="_blank">Shopware</a>
            </p>
        `);

        // select "No Link"
        const paragraphWithoutLink = document.getElementById('paragraphWithoutLink');
        await addAndCheckSelection(wrapper, paragraphWithoutLink, 0, 7, 'No link');
        document.dispatchEvent(new Event('mouseup'));

        // click on link button
        const linkButtonIcon = wrapper.find('.sw-text-editor-toolbar-button__type-link .sw-text-editor-toolbar-button__icon');
        await linkButtonIcon.trigger('click');
        await flushPromises();

        // link menu should be opened
        const linkMenu = wrapper.find('.sw-text-editor-toolbar-button__link-menu');
        expect(linkMenu.exists()).toBe(true);

        // input field should contain the correct url value
        const linkInput = linkMenu.find('#sw-field--linkTarget');
        expect(linkInput.exists()).toBe(true);
        expect(linkInput.element.value).toBe('');

        // switch field should contain correct newTab value
        const newTabSwitch = wrapper.find('.sw-text-editor-toolbar-button__link-menu-new-tab input');
        expect(newTabSwitch.element.checked).toBe(false);
    });

    it('should be able to switch from active link to non link text', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, `
            <p id="paragraphWithoutLink">No link</p>

            <p id="paragraphWithLink">
                <a id="linkText" href="http://shopware.com" target="_blank">Shopware</a>
            </p>
        `);

        // select "Shopware"
        const linkText = document.getElementById('linkText');
        await addAndCheckSelection(wrapper, linkText, 0, 8, 'Shopware');
        document.dispatchEvent(new Event('mouseup'));

        // click on link button
        let linkButtonIcon = wrapper.find('.sw-text-editor-toolbar-button__type-link .sw-text-editor-toolbar-button__icon');
        await linkButtonIcon.trigger('click');
        await flushPromises();

        // link menu should be opened
        let linkMenu = wrapper.find('.sw-text-editor-toolbar-button__link-menu');
        expect(linkMenu.exists()).toBe(true);

        // input field should contain the correct url value
        let linkInput = linkMenu.find('#sw-field--linkTarget');
        expect(linkInput.exists()).toBe(true);
        expect(linkInput.element.value).toBe('http://shopware.com');

        // switch field should contain correct newTab value
        let newTabSwitch = wrapper.find('.sw-text-editor-toolbar-button__link-menu-new-tab input');
        expect(newTabSwitch.element.checked).toBe(true);

        // select "No Link" after the "Shopware" link was selected before
        const paragraphWithoutLink = document.getElementById('paragraphWithoutLink');
        await clearSelection(wrapper);
        await addAndCheckSelection(wrapper, paragraphWithoutLink, 0, 7, 'No link');
        document.dispatchEvent(new Event('mouseup'));

        // click on link button
        linkButtonIcon = wrapper.find('.sw-text-editor-toolbar-button__type-link .sw-text-editor-toolbar-button__icon');
        await linkButtonIcon.trigger('click');

        // link menu should be opened
        linkMenu = wrapper.find('.sw-text-editor-toolbar-button__children');
        expect(linkMenu.exists()).toBe(true);

        // input field should contain the correct url value
        linkInput = linkMenu.find('#sw-field--linkTarget');
        expect(linkInput.exists()).toBe(true);
        expect(linkInput.element.value).toBe('');

        // switch field should contain correct newTab value
        newTabSwitch = wrapper.find('.sw-text-editor-toolbar-button__link-menu-new-tab input');
        expect(newTabSwitch.element.checked).toBe(false);
    });

    it('should be able to switch from one link to another link', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, `
            <a id="linkOne" href="http://shopware.com" target="_self">Shopware</a>
            <a id="linkTwo" href="http://google.com" target="_blank">Google</a>
        `);

        // select "Shopware"
        const linkOne = document.getElementById('linkOne');
        await addAndCheckSelection(wrapper, linkOne, 0, 8, 'Shopware');
        document.dispatchEvent(new Event('mouseup'));

        // click on link button
        let linkButtonIcon = wrapper.find('.sw-text-editor-toolbar-button__type-link .sw-text-editor-toolbar-button__icon');
        await linkButtonIcon.trigger('click');
        await flushPromises();

        // link menu should be opened
        let linkMenu = wrapper.find('.sw-text-editor-toolbar-button__link-menu');
        expect(linkMenu.exists()).toBe(true);

        // input field should contain the correct url value
        let linkInput = linkMenu.find('#sw-field--linkTarget');
        expect(linkInput.exists()).toBe(true);
        expect(linkInput.element.value).toBe('http://shopware.com');

        // switch field should contain correct newTab value
        let newTabSwitch = wrapper.find('.sw-text-editor-toolbar-button__link-menu-new-tab input');
        expect(newTabSwitch.element.checked).toBe(false);

        // select "Google" after the "Shopware" link was selected before
        const linkTwo = document.getElementById('linkTwo');
        await clearSelection(wrapper);
        await addAndCheckSelection(wrapper, linkTwo, 0, 6, 'Google');
        document.dispatchEvent(new Event('mouseup'));

        // click on link button
        linkButtonIcon = wrapper.find('.sw-text-editor-toolbar-button__type-link .sw-text-editor-toolbar-button__icon');
        await linkButtonIcon.trigger('click');
        await wrapper.vm.$nextTick();

        // link menu should be opened
        linkMenu = wrapper.find('.sw-text-editor-toolbar-button__link-menu');
        expect(linkMenu.exists()).toBe(true);

        // input field should contain the correct url value
        linkInput = linkMenu.find('#sw-field--linkTarget');
        expect(linkInput.exists()).toBe(true);
        expect(linkInput.element.value).toBe('http://google.com');

        // switch field should contain correct newTab value
        newTabSwitch = wrapper.find('.sw-text-editor-toolbar-button__link-menu-new-tab input');
        expect(newTabSwitch.element.checked).toBe(true);
    });

    it('should remove link from text', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, '<a href="http://shopware.com" target="_blank"><bold><u>Shop<strike id="anchor">ware</strike></u></bold></a>');

        // select "ware"
        const linkOne = document.getElementById('anchor');
        await addAndCheckSelection(wrapper, linkOne, 0, 4, 'ware');
        document.dispatchEvent(new Event('mouseup'));

        // click on link button
        await wrapper.get('.sw-text-editor-toolbar-button__type-link .sw-text-editor-toolbar-button__icon').trigger('click');
        await flushPromises();

        // link menu should be opened
        const linkMenu = wrapper.find('.sw-text-editor-toolbar-button__link-menu');
        expect(linkMenu.exists()).toBe(true);

        // trigger the link removal
        const removeButton = await wrapper.get('.sw-text-editor-toolbar-button__link-menu-buttons-button-remove');
        removeButton.disabled = '';
        await removeButton.trigger('click');

        // check that the link got removed
        expect(wrapper.vm.getContentValue()).toBe('<bold><u>Shop<strike id="anchor">ware</strike></u></bold>');
    });

    it('should let the toolbar disappear, when containing component unmounts', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, '<a href="http://shopware.com" target="_blank"><bold><u id="content">Shopware</u></bold></a>');

        // select anything to trigger the toolbar
        const content = document.getElementById('content');
        await addAndCheckSelection(wrapper, content, 0, 4, 'Shop');
        document.dispatchEvent(new Event('mouseup'));

        // click on link button
        await wrapper.get('.sw-text-editor-toolbar-button__type-link .sw-text-editor-toolbar-button__icon').trigger('click');
        await flushPromises();

        // link menu should be opened
        let linkMenu = wrapper.find('.sw-text-editor-toolbar-button__link-menu');
        expect(linkMenu.exists()).toBe(true);

        // unmount component
        await wrapper.unmount();
        await flushPromises();

        linkMenu = wrapper.find('.sw-text-editor-toolbar-button__link-menu');
        expect(linkMenu.exists()).toBe(false);
    });

    it("should leave the text alone, if there isn't link to be removed", async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, '<bold><u>Shop<strike id="anchor">ware</strike></u></bold>');

        // select "ware"
        const linkOne = document.getElementById('anchor');
        await addAndCheckSelection(wrapper, linkOne, 0, 4, 'ware');
        document.dispatchEvent(new Event('mouseup'));

        // click on link button
        await wrapper.get('.sw-text-editor-toolbar-button__type-link .sw-text-editor-toolbar-button__icon').trigger('click');
        await flushPromises();

        // link menu should be opened
        const linkMenu = wrapper.find('.sw-text-editor-toolbar-button__link-menu');
        expect(linkMenu.exists()).toBe(true);

        // trigger the link removal
        await wrapper.get('.sw-text-editor-toolbar-button__link-menu-buttons-button-remove').trigger('click');

        // check that the link got removed
        expect(wrapper.vm.getContentValue()).toBe('<bold><u>Shop<strike id="anchor">ware</strike></u></bold>');
    });

    it('should copy html from the wysiwyg mode and ignore p elements', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, '<bold><p><u>Shop<strike id="anchor">ware</strike></u></p></bold>');

        // select "ware"
        const textNode = document.getElementById('anchor');
        await addAndCheckSelection(wrapper, textNode, 0, 4, 'ware');
        document.dispatchEvent(new Event('mouseup'));

        // should have copied the text with and without styling
        const setData = jest.fn();

        await wrapper.get('.sw-text-editor__content-editor').trigger('copy', { clipboardData: { setData } });

        expect(setData.mock.calls).toContainEqual(
            ['text/html', '<strike><u><bold>ware</bold></u></strike>'],
            ['text/plain', 'ware'],
        );
    });

    it('should paste html styled text if the shift key is not pressed', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, '<span id="anchor">ware</span>');

        // select "ware"
        const textNode = document.getElementById('anchor');
        await addAndCheckSelection(wrapper, textNode, 0, 4, 'ware');
        document.dispatchEvent(new Event('mouseup'));

        // prepare getData mock
        const getData = jest.fn().mockImplementation((type) => {
            switch (type) {
                case 'text/plain':
                    return 'test';
                case 'text/html':
                    return '<strike><u><bold>test</bold></u></strike>';
                default:
                    throw new Error(`The mime type ${type} is not supported`);
            }
        });

        // release shift
        wrapper.vm.keyListener({ shiftKey: false });

        // paste styled 'test' over 'ware'
        await wrapper.get('.sw-text-editor__content-editor').trigger('paste', { clipboardData: { getData } });
        expect(getData.mock.calls).toEqual([['text/plain'], ['text/html']]);
        expect(wrapper.vm.getContentValue()).toBe('<span id=\"anchor\"><strike><u><bold>test</bold></u></strike></span>');
    });

    it('should paste text instead of html when the shift key is pressed', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        await addTextToEditor(wrapper, '<span id="anchor">ware</span>');

        // select "ware"
        const textNode = document.getElementById('anchor');
        await addAndCheckSelection(wrapper, textNode, 0, 4, 'ware');
        document.dispatchEvent(new Event('mouseup'));

        // prepare getData mock
        const getData = jest.fn().mockImplementation((type) => {
            switch (type) {
                case 'text/plain':
                    return 'test';
                case 'text/html':
                    return '<strike><u><bold>test</bold></u></strike>';
                default:
                    throw new Error(`The mime type ${type} is not supported`);
            }
        });

        // press shift
        wrapper.vm.keyListener({ shiftKey: true });

        // paste styled 'test' over 'ware'
        await wrapper.get('.sw-text-editor__content-editor').trigger('paste', { clipboardData: { getData } });
        expect(getData.mock.calls).toEqual([['text/plain'], ['text/html']]);
        expect(wrapper.vm.getContentValue()).toBe('<span id=\"anchor\">test</span>');
    });

    it('should fall back to pasting text into the wysiwyg editor if html isn\'t available', async () => {
        wrapper = await createWrapper();

        await addTextToEditor(wrapper, '<span id="anchor">ware</span>');

        // select "ware"
        const textNode = document.getElementById('anchor');
        await addAndCheckSelection(wrapper, textNode, 0, 4, 'ware');
        document.dispatchEvent(new Event('mouseup'));

        // prepare getData mock
        const getData = jest.fn().mockImplementation((type) => {
            switch (type) {
                case 'text/plain':
                    return 'test';
                case 'text/html':
                    return '';
                default:
                    throw new Error(`The mime type ${type} is not supported`);
            }
        });

        // paste 'test' over 'ware'
        await wrapper.get('.sw-text-editor__content-editor').trigger('paste', { clipboardData: { getData } });
        expect(getData.mock.calls).toEqual([['text/plain'], ['text/html']]);
        expect(wrapper.vm.getContentValue()).toBe('<span id=\"anchor\">test</span>');
    });

    it('should not render transparent background', async () => {
        wrapper = await createWrapper(false);

        expect(wrapper.find('.sw-text-editor__content').classes()).not.toContain('is--transparent-background');
    });

    it('should render transparent background', async () => {
        wrapper = await createWrapper(false);
        await wrapper.setProps({
            enableTransparentBackground: true,
        });

        expect(wrapper.find('.sw-text-editor__content').classes()).toContain('is--transparent-background');
    });

    it('should render the inline toolbar when editor is not disabled', async () => {
        wrapper = await createWrapper();

        await wrapper.setProps({
            isInlineEdit: true,
        });

        await addTextToEditor(wrapper, '<p id="paragraph">Hello World</p>');

        // Add selection
        const paragraph = document.getElementById('paragraph');
        await addAndCheckSelection(wrapper, paragraph, 0, 11, 'Hello World');

        // Check if the inline toolbar is visible in body DOM
        await flushPromises();

        expect(document.body.querySelector('.sw-text-editor-toolbar')).toBeTruthy();
    });

    it('should not render the inline toolbar when editor is disabled', async () => {
        wrapper = await createWrapper();

        await wrapper.setProps({
            isInlineEdit: true,
            disabled: true,
        });

        await addTextToEditor(wrapper, '<p id="paragraph">Hello World</p>');

        // Add selection
        const paragraph = document.getElementById('paragraph');
        await addAndCheckSelection(wrapper, paragraph, 0, 11, 'Hello World');

        // Check if the inline toolbar is visible in body DOM
        await flushPromises();

        expect(document.body.querySelector('.sw-text-editor-toolbar')).toBeNull();
    });
});
