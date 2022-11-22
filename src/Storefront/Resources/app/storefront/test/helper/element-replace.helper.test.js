import ElementReplaceHelper from 'src/helper/element-replace.helper';
import template from './element-replace.helper.template.html';

/**
 * @package storefront
 */
describe('element-replace.helper', () => {
    beforeEach(() => {
        document.body.innerHTML = template;
    });

    describe('replaceFromMarkup', () => {
        test('it can replace an element from string markup', () => {
            const markup = '<div class="replaceable-content"><p>replaced content</p></div>'

            ElementReplaceHelper.replaceFromMarkup(markup, '.replaceable-content');

            const replaced = document.querySelector('.replaceable-content');
            expect(replaced).not.toBeNull();
            expect(replaced.textContent).toBe('replaced content');
        });

        test('it can replace an element from another', () => {
            const markup = (new DOMParser()).parseFromString(
                '<div class="replaceable-content"><p>replaced content</p></div>',
                'text/html'
            );

            ElementReplaceHelper.replaceFromMarkup(markup, ['.replaceable-content'], true);

            const replaced = document.querySelector('.replaceable-content');
            expect(replaced).not.toBeNull();
            expect(replaced.textContent).toBe('replaced content');
        });

        test('it does not replace things in none strict mode', () => {
            const markup = '<div class="replaceable-content"></div>';
            ElementReplaceHelper.replaceFromMarkup(markup, '.i-wont-be-found', false);

            expect()
        })
    });

    describe('replaceElement', () => {
        test('it returns false for missing elements', () => {
            expect(ElementReplaceHelper.replaceElement(null, null)).toBe(false);
            expect(ElementReplaceHelper.replaceElement(null, document.createElement('div'))).toBe(false);
            expect(ElementReplaceHelper.replaceElement(document.createElement('div'), document.createElement('div'))).toBe(false);
        });

        test('it can can replace content if only specific elements are given', () => {
            const src = document.createElement('div');
            src.innerHTML = '<p>this is the replacement</p>';
            const target = document.querySelector('.replaceable-content');

            ElementReplaceHelper.replaceElement(src, target);

            expect(target.innerHTML).toBe('<p>this is the replacement</p>');
            expect(target.classList.contains('replaceable-content'));
        });

        test('it can replace the content of node lists at exact size', () => {
            const listEntries = (new DOMParser()).parseFromString(
                `
                <ul>
                    <li class="replaced-item replaced--0">this is replaced</li>
                    <li class="replaced-item replaced--1">this is replaced</li>
                    <li class="replaced-item replaced--2">this is replaced</li>
                    <li class="replaced-item replaced--3">this is replaced</li>
                </ul>
                `,
                'text/html'
            );

            ElementReplaceHelper.replaceElement(
                listEntries.querySelectorAll('li.replaced-item'),
                document.querySelectorAll('li.replaceable-item')
            );

            const replacedItems = document.querySelectorAll('li.replaceable-item');
            expect(replacedItems).toHaveProperty('length', 4);
            replacedItems.forEach((node) => {
                expect(node.textContent).toBe('this is replaced');
            })
        });

        test('it throws if src has more elements than target', () => {
            const listEntries = (new DOMParser()).parseFromString(
                `
            <ul>
                <li class="replaced-item replaced--0">this is replaced</li>
                <li class="replaced-item replaced--1">this is replaced</li>
                <li class="replaced-item replaced--2">this is replaced</li>
                <li class="replaced-item replaced--3">this is replaced</li>
                <li class="replaced-item replaced--4">this is replaced</li>
            </ul>
            `,
                'text/html'
            );

            expect(() => {
                ElementReplaceHelper.replaceElement(
                    listEntries.querySelectorAll('li.replaced-item'),
                    document.querySelectorAll('li.replaceable-item')
                );
            }).toThrowError();
        });

        test('it does not remove content if innerHtml is empty', () => {
            const listEntries = (new DOMParser()).parseFromString(
                `
                <ul>
                    <li class="replaced-item replaced--0"></li>
                    <li class="replaced-item replaced--1"></li>
                    <li class="replaced-item replaced--2"></li>
                    <li class="replaced-item replaced--3"></li>
                </ul>
                `,
                'text/html'
            );

            ElementReplaceHelper.replaceElement(
                listEntries.querySelectorAll('li.replaced-item'),
                document.querySelectorAll('li.replaceable-item')
            );

            const replacedItems = document.querySelectorAll('li.replaceable-item');
            expect(replacedItems).toHaveProperty('length', 4);
            replacedItems.forEach((node, index) => {
                expect(node.textContent).toBe('original content');
            })
        });

        test('it can copy content from one node to another by passing selectors', () => {
            ElementReplaceHelper.replaceElement('div.copy-source', 'div.copy-target');

            const target = document.querySelector('div.copy-target');
            expect(target.textContent).toBe('this text should be copied');
        });

        test('it can update a list with a given elements content', () => {
            const src = document.createElement('li');
            src.innerHTML = '<p>updated content</p>';

            const target = document.querySelectorAll('li.replaceable-item');
            ElementReplaceHelper.replaceElement(src, target);

            expect(target).toHaveProperty('length', 4);
            target.forEach((listElement) => {
                expect(listElement.textContent).toBe('updated content');
            })
        });

        test('it does not remove content if single src has none', () => {
            const src = document.createElement('div');
            const target = document.querySelectorAll('li.replaceable-item');

            ElementReplaceHelper.replaceElement(src, target);

            expect(target).toHaveProperty('length', 4);
            target.forEach((listElement) => {
                expect(listElement.textContent).toBe('original content');
            })
        });

        test('it returns with false if source or target are not found for none strict node', () => {
            expect(ElementReplaceHelper.replaceElement('.nothing', '.nothing', false)).toBe(false);
        });

        test('it should replace all target element have similar classname', () => {
            const src = (new DOMParser()).parseFromString(
                `
                <ul>
                    <li class="replaced-item replaced--0">this is replaced</li>
                    <li class="replaced-item replaced--1">this is replaced 1</li>
                </ul>
                `,
                'text/html'
            );

            const target = (new DOMParser()).parseFromString(
                `
                <ul>
                    <li class="replaced-item replaced--0">original content</li>
                    <li class="replaced-item replaced--1">original content 1</li>
                    <li class="replaced-item replaced--0">original content</li>
                    <li class="replaced-item replaced--1">original content 1</li>
                </ul>
                `,
                'text/html'
            );

            ElementReplaceHelper.replaceElement(
                src.querySelectorAll('li.replaced-item'),
                target.querySelectorAll('li.replaced-item')
            );

            const replacedItems = target.querySelectorAll('li.replaced-item');
            expect(replacedItems).toHaveProperty('length', 4);

            replacedItems.forEach((node, index) => {
                if (index % 2 === 0) {
                    expect(node.textContent).toBe('this is replaced');
                } else {
                    expect(node.textContent).toBe('this is replaced 1');
                }
            })
        });
    });
});
