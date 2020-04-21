import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-text-editor/sw-text-editor-toolbar';

const testCases = [{
    link: '124c71d524604ccbad6042edce3ac799',
    result: false
}, {
    link: '/detail/124c71d524604ccbad6042edce3ac799',
    result: false
}, {
    link: 'www.domain.de/test',
    result: true
}, {
    link: 'domain.de',
    result: true
}, {
    link: 'http://domain.de',
    result: true
}];

let wrapper;

beforeEach(() => {
    wrapper = shallowMount(Shopware.Component.build('sw-text-editor-toolbar'), {
        stubs: {
            'sw-text-editor-toolbar-button': true
        },
        propsData: {
            buttonConfig: [
                {
                    type: 'paragraph',
                    icon: 'default-text-editor-style',
                    children: [
                        {
                            type: 'formatBlock',
                            name: 'Paragraph',
                            value: 'p',
                            tag: 'p'
                        },
                        {
                            type: 'formatBlock',
                            name: 'Heading 1',
                            value: 'h1',
                            tag: 'h1'
                        },
                        {
                            type: 'formatBlock',
                            name: 'Heading 2',
                            value: 'h2',
                            tag: 'h2'
                        },
                        {
                            type: 'formatBlock',
                            name: 'Heading 3',
                            value: 'h3',
                            tag: 'h3'
                        },
                        {
                            type: 'formatBlock',
                            name: 'Heading 4',
                            value: 'h4',
                            tag: 'h4'
                        },
                        {
                            type: 'formatBlock',
                            name: 'Heading 5',
                            value: 'h5',
                            tag: 'h5'
                        },
                        {
                            type: 'formatBlock',
                            name: 'Heading 6',
                            value: 'h6',
                            tag: 'h6'
                        },
                        {
                            type: 'formatBlock',
                            name: 'Blockquote',
                            value: 'blockquote',
                            tag: 'blockquote'
                        }
                    ]
                },
                {
                    type: 'foreColor',
                    value: '',
                    tag: 'font'
                },
                {
                    type: 'bold',
                    icon: 'default-text-editor-bold',
                    tag: 'b'
                },
                {
                    type: 'italic',
                    icon: 'default-text-editor-italic',
                    tag: 'i'
                },
                {
                    type: 'underline',
                    icon: 'default-text-editor-underline',
                    tag: 'u'
                },
                {
                    type: 'strikethrough',
                    icon: 'default-text-editor-strikethrough',
                    tag: 'strike'
                },
                {
                    type: 'superscript',
                    icon: 'default-text-editor-superscript',
                    tag: 'sup'
                },
                {
                    type: 'subscript',
                    icon: 'default-text-editor-subscript',
                    tag: 'sub'
                },
                {
                    type: 'justify',
                    icon: 'default-text-editor-align-left',
                    children: [
                        {
                            type: 'justifyLeft',
                            icon: 'default-text-align-left'
                        },
                        {
                            type: 'justifyCenter',
                            icon: 'default-text-align-center'
                        },
                        {
                            type: 'justifyRight',
                            icon: 'default-text-align-right'
                        },
                        {
                            type: 'justifyFull',
                            icon: 'default-text-align-justify'
                        }
                    ]
                },
                {
                    type: 'insertUnorderedList',
                    icon: 'default-text-editor-list-unordered',
                    tag: 'ul'
                },
                {
                    type: 'insertOrderedList',
                    icon: 'default-text-editor-list-numberd',
                    tag: 'ol'
                },
                {
                    type: 'link',
                    icon: 'default-text-editor-link',
                    expanded: false,
                    newTab: false,
                    displayAsButton: false,
                    buttonVariant: '',
                    buttonVariantList: [
                        {
                            id: 'primary',
                            name: 'sw-text-editor-toolbar.link.buttonVariantPrimary'
                        },
                        {
                            id: 'secondary',
                            name: 'sw-text-editor-toolbar.link.buttonVariantSecondary'
                        },
                        {
                            id: 'primary-sm',
                            name: 'sw-text-editor-toolbar.link.buttonVariantPrimarySmall'
                        },
                        {
                            id: 'secondary-sm',
                            name: 'sw-text-editor-toolbar.link.buttonVariantSecondarySmall'
                        }
                    ],
                    value: '',
                    tag: 'a'
                },
                {
                    type: 'undo',
                    icon: 'default-text-editor-undo',
                    position: 'middle'
                },
                {
                    type: 'redo',
                    icon: 'default-text-editor-redo',
                    position: 'middle'
                }
            ]
        }
    });
});

describe('components/form/sw-text-editor/sw-text-editor-toolbar', () => {
    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });
    it('it should apply http correctly', () => {
        testCases.forEach(({ link, result }) => {
            expect(wrapper.vm.prepareLink(link).startsWith('http://')).toBe(result);
        });
    });
});
