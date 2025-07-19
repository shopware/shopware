/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import { MtTextEditorToolbarButton, MtModalClose, MtModal, MtModalRoot } from '@shopware-ag/meteor-component-library';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/category',
    status: 200,
    response: {
        data: [
            {
                id: 'YourId',
                attributes: {
                    id: 'YourId',
                },
                relationships: [],
            },
        ],
    },
});

const seoUrlPrefixId = '124c71d524604ccbad6042edce3ac799';

function findByText(wrap, selector, text) {
    return wrap.findAll(selector).filter((n) => n.text().match(text));
}

function createEditorStub() {
    const editorStub = {
        getAttributes: (attribute) => {
            if (attribute === 'link') {
                return {
                    href: 'https://www.shopware.com',
                    target: null,
                };
            }

            return undefined;
        },
        isActive: () => false,
        chain: () => editorStub,
        focus: () => editorStub,
        extendMarkRange: () => editorStub,
        setLink: jest.fn(() => editorStub),
        run: () => editorStub,
    };

    return editorStub;
}

async function createWrapper(
    additionalOptions = {
        slots: {},
        props: {},
    },
) {
    const editorStub = createEditorStub();
    const buttonStub = {
        label: 'stub.label',
    };

    return mount(await wrapTestComponent('sw-text-editor-toolbar-button-link', { sync: true }), {
        props: {
            editor: editorStub,
            button: buttonStub,
            ...additionalOptions.props,
        },
        slots: {
            ...additionalOptions.slots,
        },
        global: {
            stubs: {
                'mt-text-editor-toolbar-button': MtTextEditorToolbarButton,
                'mt-modal-close': MtModalClose,
                'mt-modal': MtModal,
                'mt-modal-root': MtModalRoot,
                'sw-entity-single-select': true,
                'sw-category-tree-field': true,
                'sw-media-field': true,
                teleport: true,
            },
        },
    });
}

describe('src/app/component/meteor-wrapper/mt-text-editor/mt-text-editor-toolbar-button-link', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the button', async () => {
        const wrapper = await createWrapper();

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Expect button to be rendered
        expect(button.exists()).toBe(true);
    });

    it('should open the modal on button click', async () => {
        const wrapper = await createWrapper();

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Check if modal title is rendered inside h2
        expect(wrapper.find('h2').text()).toBe('sw-text-editor-toolbar-button-link.modalTitle');
    });

    it('should render the modal with the correct fields (linkType = "link")', async () => {
        const wrapper = await createWrapper();

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Check all fields
        const labels = wrapper.findAll('label');
        expect(labels).toHaveLength(4);

        // Check if field with label "sw-text-editor-toolbar-button-link.linkType" is rendered
        expect(labels[0].text()).toBe('sw-text-editor-toolbar-button-link.linkType');
        // Check if field with label "sw-text-editor-toolbar-button-link.linkUrl" is rendered
        expect(labels[1].text()).toBe('sw-text-editor-toolbar-button-link.linkUrl');
        // Check if field with label "sw-text-editor-toolbar-button-link.openInNewTab" is rendered
        expect(labels[2].text()).toBe('sw-text-editor-toolbar-button-link.openInNewTab');
        // Check if field with label "sw-text-editor-toolbar-button-link.displayAsButton" is rendered
        expect(labels[3].text()).toBe('sw-text-editor-toolbar-button-link.displayAsButton');
    });

    it('should render the modal with the correct fields (linkType = "detail")', async () => {
        const wrapper = await createWrapper({
            props: {
                editor: {
                    getAttributes: (attribute) => {
                        if (attribute === 'link') {
                            return {
                                href: `${seoUrlPrefixId}/detail/exampleId/`,
                                target: 'stub.target',
                                rel: 'stub.rel',
                                title: 'stub.title',
                            };
                        }

                        return undefined;
                    },
                    isActive: () => true,
                },
            },
        });

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Check all fields
        const labels = wrapper.findAll('label');
        expect(labels).toHaveLength(3);

        // Check if field with label "sw-text-editor-toolbar-button-link.linkType" is rendered
        expect(labels[0].text()).toBe('sw-text-editor-toolbar-button-link.linkType');
        // Check if field with label "sw-text-editor-toolbar-button-link.openInNewTab" is rendered
        expect(labels[1].text()).toBe('sw-text-editor-toolbar-button-link.openInNewTab');
        // Check if field with label "sw-text-editor-toolbar-button-link.displayAsButton" is rendered
        expect(labels[2].text()).toBe('sw-text-editor-toolbar-button-link.displayAsButton');

        // Check if sw-entity-single-select is rendered with label "sw-text-editor-toolbar-button-link.linkDetail"
        const entitySingleSelect = wrapper.find('sw-entity-single-select-stub');
        expect(entitySingleSelect.exists()).toBe(true);
        expect(entitySingleSelect.attributes('label')).toBe('sw-text-editor-toolbar-button-link.linkDetail');
    });

    it('should render the modal with the correct fields (linkType = "navigation")', async () => {
        const wrapper = await createWrapper({
            props: {
                editor: {
                    getAttributes: (attribute) => {
                        if (attribute === 'link') {
                            return {
                                href: `${seoUrlPrefixId}/navigation/exampleId/`,
                                target: 'stub.target',
                                rel: 'stub.rel',
                                title: 'stub.title',
                            };
                        }

                        return undefined;
                    },
                    isActive: () => true,
                },
            },
        });

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');
        await flushPromises();

        // Check all fields
        const labels = wrapper.findAll('label');
        expect(labels).toHaveLength(3);

        // Check if field with label "sw-text-editor-toolbar-button-link.linkType" is rendered
        expect(labels[0].text()).toBe('sw-text-editor-toolbar-button-link.linkType');
        // Check if field with label "sw-text-editor-toolbar-button-link.openInNewTab" is rendered
        expect(labels[1].text()).toBe('sw-text-editor-toolbar-button-link.openInNewTab');
        // Check if field with label "sw-text-editor-toolbar-button-link.displayAsButton" is rendered
        expect(labels[2].text()).toBe('sw-text-editor-toolbar-button-link.displayAsButton');

        // Check if sw-category-tree-field is rendered with label "sw-text-editor-toolbar-button-link.linkCategory"
        const categoryTreeField = wrapper.find('sw-category-tree-field-stub');
        expect(categoryTreeField.exists()).toBe(true);
        expect(categoryTreeField.attributes('label')).toBe('sw-text-editor-toolbar-button-link.linkTo');
        expect(categoryTreeField.attributes('placeholder')).toBe('sw-text-editor-toolbar-button-link.categoryPlaceholder');
    });

    it('should render the modal with the correct fields (linkType = "media")', async () => {
        const wrapper = await createWrapper({
            props: {
                editor: {
                    getAttributes: (attribute) => {
                        if (attribute === 'link') {
                            return {
                                href: `${seoUrlPrefixId}/mediaId/exampleId/`,
                                target: 'stub.target',
                                rel: 'stub.rel',
                                title: 'stub.title',
                            };
                        }

                        return undefined;
                    },
                    isActive: () => true,
                },
            },
        });

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Check all fields
        const labels = wrapper.findAll('label');
        expect(labels).toHaveLength(3);

        // Check if field with label "sw-text-editor-toolbar-button-link.linkType" is rendered
        expect(labels[0].text()).toBe('sw-text-editor-toolbar-button-link.linkType');
        // Check if field with label "sw-text-editor-toolbar-button-link.openInNewTab" is rendered
        expect(labels[1].text()).toBe('sw-text-editor-toolbar-button-link.openInNewTab');
        // Check if field with label "sw-text-editor-toolbar-button-link.displayAsButton" is rendered
        expect(labels[2].text()).toBe('sw-text-editor-toolbar-button-link.displayAsButton');

        // Check if sw-media-field is rendered with label "sw-text-editor-toolbar-button-link.linkMedia"
        const mediaField = wrapper.find('sw-media-field-stub');
        expect(mediaField.exists()).toBe(true);
        expect(mediaField.attributes('label')).toBe('sw-text-editor-toolbar-button-link.linkTo');
    });

    it('should render the modal with the correct fields (linkType = "email")', async () => {
        const wrapper = await createWrapper({
            props: {
                editor: {
                    getAttributes: (attribute) => {
                        if (attribute === 'link') {
                            return {
                                href: 'mailto:test@example.com',
                                target: 'stub.target',
                                rel: 'stub.rel',
                                title: 'stub.title',
                            };
                        }

                        return undefined;
                    },
                    isActive: () => true,
                },
            },
        });

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Check all fields
        const labels = wrapper.findAll('label');
        expect(labels).toHaveLength(3);

        // Check if field with label "sw-text-editor-toolbar-button-link.linkType" is rendered
        expect(labels[0].text()).toBe('sw-text-editor-toolbar-button-link.linkType');
        // Check if field with label "sw-text-editor-toolbar-button-link.linkEmail" is rendered
        expect(labels[1].text()).toBe('sw-text-editor-toolbar-button-link.linkEmail');
        // Check if field with label "sw-text-editor-toolbar-button-link.displayAsButton" is rendered
        expect(labels[2].text()).toBe('sw-text-editor-toolbar-button-link.displayAsButton');
    });

    it('should render the modal with the correct fields (linkType = "phone")', async () => {
        const wrapper = await createWrapper({
            props: {
                editor: {
                    getAttributes: (attribute) => {
                        if (attribute === 'link') {
                            return {
                                href: 'tel:+123456789',
                                target: 'stub.target',
                                rel: 'stub.rel',
                                title: 'stub.title',
                            };
                        }

                        return undefined;
                    },
                    isActive: () => true,
                },
            },
        });

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Check all fields
        const labels = wrapper.findAll('label');
        expect(labels).toHaveLength(3);

        // Check if field with label "sw-text-editor-toolbar-button-link.linkType" is rendered
        expect(labels[0].text()).toBe('sw-text-editor-toolbar-button-link.linkType');
        // Check if field with label "sw-text-editor-toolbar-button-link.linkPhone" is rendered
        expect(labels[1].text()).toBe('sw-text-editor-toolbar-button-link.linkPhone');
        // Check if field with label "sw-text-editor-toolbar-button-link.displayAsButton" is rendered
        expect(labels[2].text()).toBe('sw-text-editor-toolbar-button-link.displayAsButton');
    });

    it('should insert the correct link (linkType = "link")', async () => {
        const wrapper = await createWrapper();

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Update values
        const linkUrlInput = wrapper.find('input[aria-label="sw-text-editor-toolbar-button-link.linkUrl"]');

        await linkUrlInput.setValue('https://www.shopware.com');

        // Get button with text content "sw-text-editor-toolbar-button-link.applyLink"
        const applyButton = findByText(wrapper, 'button', 'sw-text-editor-toolbar-button-link.applyLink')[0];

        // Click on apply button
        await applyButton.trigger('click');

        // Check if setLink was called with correct values
        expect(wrapper.props().editor.setLink).toHaveBeenCalledWith({
            href: 'https://www.shopware.com',
            target: '',
            class: undefined,
        });
    });

    it('should insert the correct link with _blank (linkType = "link")', async () => {
        const wrapper = await createWrapper();

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Update values
        const linkUrlInput = wrapper.find('input[aria-label="sw-text-editor-toolbar-button-link.linkUrl"]');
        await linkUrlInput.setValue('https://www.shopware.com');

        const openInNewTabCheckbox = wrapper.find('.sw-text-editor-toolbar-button-link__open-in-new-tab-switch input');
        await openInNewTabCheckbox.setChecked(true);

        // Get button with text content "sw-text-editor-toolbar-button-link.applyLink"
        const applyButton = findByText(wrapper, 'button', 'sw-text-editor-toolbar-button-link.applyLink')[0];

        // Click on apply button
        await applyButton.trigger('click');

        // Check if setLink was called with correct values
        expect(wrapper.props().editor.setLink).toHaveBeenCalledWith({
            href: 'https://www.shopware.com',
            target: '_blank',
            class: undefined,
        });
    });

    it('should insert the correct link as button (linkType = "link")', async () => {
        const wrapper = await createWrapper();

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Update values
        const linkUrlInput = wrapper.find('input[aria-label="sw-text-editor-toolbar-button-link.linkUrl"]');
        await linkUrlInput.setValue('https://www.shopware.com');

        const displayAsButtonCheckbox = wrapper.find('.sw-text-editor-toolbar-button-link__display-as-button-switch input');
        await displayAsButtonCheckbox.setChecked(true);

        // Get button with text content "sw-text-editor-toolbar-button-link.applyLink"
        const applyButton = findByText(wrapper, 'button', 'sw-text-editor-toolbar-button-link.applyLink')[0];

        // Click on apply button
        await applyButton.trigger('click');

        // Check if setLink was called with correct values
        expect(wrapper.props().editor.setLink).toHaveBeenCalledWith({
            href: 'https://www.shopware.com',
            target: '',
            class: 'btn btn-primary',
        });
    });

    it('should insert the correct link (linkType = "detail")', async () => {
        const wrapper = await createWrapper({
            props: {
                editor: {
                    ...createEditorStub(),
                    getAttributes: (attribute) => {
                        if (attribute === 'link') {
                            return {
                                href: `${seoUrlPrefixId}/detail/exampleId/`,
                                target: null,
                            };
                        }

                        return undefined;
                    },
                },
            },
        });

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Get button with text content "sw-text-editor-toolbar-button-link.applyLink"
        const applyButton = findByText(wrapper, 'button', 'sw-text-editor-toolbar-button-link.applyLink')[0];

        // Click on apply button
        await applyButton.trigger('click');

        // Check if setLink was called with correct values
        expect(wrapper.props().editor.setLink).toHaveBeenCalledWith({
            href: `${seoUrlPrefixId}/detail/exampleId#`,
            class: undefined,
            target: '',
        });
    });

    it('should insert the correct link (linkType = "navigation")', async () => {
        const wrapper = await createWrapper({
            props: {
                editor: {
                    ...createEditorStub(),
                    getAttributes: (attribute) => {
                        if (attribute === 'link') {
                            return {
                                href: `${seoUrlPrefixId}/navigation/exampleId/`,
                                target: null,
                            };
                        }

                        return undefined;
                    },
                },
            },
        });

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');
        await flushPromises();

        // Get button with text content "sw-text-editor-toolbar-button-link.applyLink"
        const applyButton = findByText(wrapper, 'button', 'sw-text-editor-toolbar-button-link.applyLink')[0];

        // Click on apply button
        await applyButton.trigger('click');

        // Check if setLink was called with correct values
        expect(wrapper.props().editor.setLink).toHaveBeenCalledWith({
            href: `${seoUrlPrefixId}/navigation/exampleId#`,
            class: undefined,
            target: '',
        });
    });

    it('should insert the correct link (linkType = "media")', async () => {
        const wrapper = await createWrapper({
            props: {
                editor: {
                    ...createEditorStub(),
                    getAttributes: (attribute) => {
                        if (attribute === 'link') {
                            return {
                                href: `${seoUrlPrefixId}/mediaId/exampleId/`,
                                target: null,
                            };
                        }

                        return undefined;
                    },
                },
            },
        });

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Get button with text content "sw-text-editor-toolbar-button-link.applyLink"
        const applyButton = findByText(wrapper, 'button', 'sw-text-editor-toolbar-button-link.applyLink')[0];

        // Click on apply button
        await applyButton.trigger('click');

        // Check if setLink was called with correct values
        expect(wrapper.props().editor.setLink).toHaveBeenCalledWith({
            href: `${seoUrlPrefixId}/mediaId/exampleId#`,
            class: undefined,
            target: '',
        });
    });

    it('should insert the correct link (linkType = "email")', async () => {
        const wrapper = await createWrapper({
            props: {
                editor: {
                    ...createEditorStub(),
                    getAttributes: (attribute) => {
                        if (attribute === 'link') {
                            return {
                                href: 'mailto:test@shopware.com',
                                target: null,
                            };
                        }

                        return undefined;
                    },
                },
            },
        });

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Get button with text content "sw-text-editor-toolbar-button-link.applyLink"
        const applyButton = findByText(wrapper, 'button', 'sw-text-editor-toolbar-button-link.applyLink')[0];

        // Click on apply button
        await applyButton.trigger('click');

        // Check if setLink was called with correct values
        expect(wrapper.props().editor.setLink).toHaveBeenCalledWith({
            href: 'mailto:test@shopware.com',
            class: undefined,
            target: null,
        });
    });

    it('should insert the correct link (linkType = "phone")', async () => {
        const wrapper = await createWrapper({
            props: {
                editor: {
                    ...createEditorStub(),
                    getAttributes: (attribute) => {
                        if (attribute === 'link') {
                            return {
                                href: 'tel:+123456789',
                                target: null,
                            };
                        }

                        return undefined;
                    },
                },
            },
        });

        // Get button with aria-label "stub.label"
        const button = wrapper.find('[aria-label="stub.label"]');

        // Click on button
        await button.trigger('click');

        // Get button with text content "sw-text-editor-toolbar-button-link.applyLink"
        const applyButton = findByText(wrapper, 'button', 'sw-text-editor-toolbar-button-link.applyLink')[0];

        // Click on apply button
        await applyButton.trigger('click');

        // Check if setLink was called with correct values
        expect(wrapper.props().editor.setLink).toHaveBeenCalledWith({
            href: 'tel:+123456789',
            class: undefined,
            target: null,
        });
    });
});
