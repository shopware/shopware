import DomAccess from 'src/helper/dom-access.helper';
import template from './dom-access.helper.template.html';

describe('dom-access.helper', () => {
    beforeEach(() => {
        document.body.innerHTML = template;
    });

    describe('isNode', () => {
        test('document is a node', () => {
            expect(DomAccess.isNode(document)).toBe(true);
        });

        test('window is a node', () => {
            expect(DomAccess.isNode(window)).toBe(true);
        });

        test('body is a node', () => {
            expect(DomAccess.isNode(document.body)).toBe(true);
            expect(DomAccess.isNode(document.querySelector('body'))).toBe(true);
        });

        test('div and h2 are nodes', () => {
            expect(DomAccess.isNode(document.querySelector('div.headline'))).toBe(true);
            expect(DomAccess.isNode(document.querySelector('h2'))).toBe(true);
        });

        test('null and undefined are no nodes', () => {
            expect(DomAccess.isNode(null)).toBe(false);
            expect(DomAccess.isNode(undefined)).toBe(false);
        });

        test('primitives are no nodes', () => {
            expect(DomAccess.isNode(21)).toBe(false);
            expect(DomAccess.isNode('<div>text node</div>')).toBe(false);
        });
    });

    describe('hasAttribute', () => {
        test('has attribute returns true if attribute exists', () => {
            const node = document.querySelector('div.headline');

            expect(DomAccess.hasAttribute(node, 'style')).toBe(true);
        });

        test('has attribute returns false if attribute does not exists', () => {
            const node = document.querySelector('div.headline');

            expect(DomAccess.hasAttribute(node, 'noExistent')).toBe(false);
        });

        test('has attribute throws for non nodes', () => {
            expect(() => { DomAccess.hasAttribute(42, 'toAnswer')}).toThrowError();
        });

        test('has attribute returns false comments', () => {
            expect(DomAccess.hasAttribute(document.createComment('this is comment', 'data-bla'))).toBe(false);
        });
    });

    describe('getAttribute', () => {
        test('returns data set in node', () => {
            const node = document.querySelector('div.headline');
            expect(DomAccess.getAttribute(node, 'style')).toBe('color: red');
        });

        test('strict getAttribute throw for non nodes', () => {
            expect(() => { DomAccess.getAttribute(42, 'theAnswer')}).toThrowError();
        });

        test('strict getAttribute throw if attribute does not exist', () => {
            const node = document.querySelector('div.headline');
            expect(() => { DomAccess.getAttribute(node, 'theAnswer')}).toThrowError();
        });

        test('strict getAttribute throws if getAttribute is not defined', () => {
            const node = document.querySelector('div.headline');
            node.getAttribute = null;

            expect(() => { DomAccess.getAttribute(node, 'style')}).toThrowError();
        });

        test('non strict getAttribute returns undefined for none nodes', () => {
            expect(DomAccess.getAttribute(42, 'theAnswer', false)).not.toBeDefined();
        });

        test('strict getAttribute throws if getAttribute is not defined', () => {
            const node = document.querySelector('div.headline');
            node.getAttribute = null;

            expect(DomAccess.getAttribute(node, 'style', false)).not.toBeDefined();
        });

        test('strict getAttribute throw if attribute does not exist', () => {
            const node = document.querySelector('div.headline');
            expect(DomAccess.getAttribute(node, 'theAnswer', false)).toBeNull();
        });
    });

    describe('getDataAttribute', () => {
        test('throws for none nodes in strict mode', () => {
            expect(() => { DomAccess.getDataAttribute(null, 'data-answers')}).toThrowError();
        });

        test('returns undefined for none nodes in none strict mode', () => {
            expect(DomAccess.getDataAttribute(null, 'data-answers', false)).not.toBeDefined();
        });

        test('throws for nodes without dataset in strict mode', () => {
            const node = document.createComment('comments do not have data');
            expect(() => { DomAccess.getDataAttribute(node, 'data-answers')}).toThrowError();
        });

        test('returns undefined for nodes without dataset in none strict mode', () => {
            const node = document.createComment('comments do not have data');
            expect(DomAccess.getDataAttribute(node, 'data-answers', false)).not.toBeDefined();
        });

        test('throws if value does not exist in strict mode', () => {
            const node = document.querySelector('div.with-object-attribute');
            expect(() => { DomAccess.getDataAttribute(node, 'data-answers')}).toThrowError();
        });

        test('returns undefined if value does not exist in none strict mode', () => {
            const node = document.querySelector('div.with-object-attribute');
            expect(DomAccess.getDataAttribute(node, 'data-answers', false)).not.toBeDefined();
        });

        test('returns parsed objects if attribute exists', () => {
            const node = document.querySelector('div.with-object-attribute');

            expect(DomAccess.getDataAttribute(node, 'x-things')).toEqual({
                a: 'a string',
                n: 50,
                s: '1.2',
            });
        });
    });

    describe('querySelector', () => {
        test('throws for none nodes and if querySelector is not defined', () => {
            const commentNode = document.createComment('this is a comment');

            expect(() => { DomAccess.querySelector(42, 'a') }).toThrowError();
            expect(() => { DomAccess.querySelector(42, 'a', false) }).toThrowError();

            expect(() => { DomAccess.querySelector(commentNode, 'a') }).toThrowError();
            expect(() => { DomAccess.querySelector(commentNode, 'a', false) }).toThrowError();
        });

        test('throws in strict mode if no element cant be found', () => {
            const emptyList = document.querySelector('ul.empty-list');
            expect(() => { DomAccess.querySelector(emptyList, 'li') }).toThrowError();
        });

        test('return false in none strict mode if no element can be found', () => {
            const emptyList = document.querySelector('ul.empty-list');
            expect(DomAccess.querySelector(emptyList, 'li', false)).toBe(false);
        });

        test('returns node when found', () => {
            const emptyList = document.querySelector('ul.non-empty-list');
            expect(DomAccess.querySelector(emptyList, 'li')).toBeInstanceOf(Node);
        });
    });

    describe('querySelectorAll', () => {
        test('throws for none nodes and if querySelectorAll is not defined', () => {
            const commentNode = document.createComment('this is a comment');

            expect(() => { DomAccess.querySelectorAll(42, 'a') }).toThrowError();
            expect(() => { DomAccess.querySelectorAll(42, 'a', false) }).toThrowError();

            expect(() => { DomAccess.querySelectorAll(commentNode, 'a') }).toThrowError();
            expect(() => { DomAccess.querySelectorAll(commentNode, 'a', false) }).toThrowError();
        });

        test('throws in strict mode if no element cant be found', () => {
            const emptyList = document.querySelector('ul.empty-list');
            expect(() => { DomAccess.querySelectorAll(emptyList, 'li') }).toThrowError();
        });

        test('return false in none strict mode if no element can be found', () => {
            const emptyList = document.querySelector('ul.empty-list');
            expect(DomAccess.querySelectorAll(emptyList, 'li', false)).toBe(false);
        });

        test('returns node when found', () => {
            const emptyList = document.querySelector('ul.non-empty-list');
            expect(DomAccess.querySelectorAll(emptyList, 'li')).toBeInstanceOf(NodeList);
        });
    });
});
