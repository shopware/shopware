import ArrowNavigationHelper from 'src/helper/arrow-navigation.helper';
import template from './arrow-navigation.helper.template.html';

const itemContainerSelector = 'ul.itemContainer';
const itemSelector = 'li';

describe('arrow-navigation.helper', () => {
    beforeEach(() => {
        document.body.innerHTML = template;
    });

    test('registers on element', () => {
        const input = document.querySelector('input');
        const navigationHelper = new ArrowNavigationHelper(
            input,
            itemContainerSelector,
            itemSelector,
            true
        );

        expect(navigationHelper._element).toBe(input);
        expect(navigationHelper._iterator).toBe(-1);
        expect(navigationHelper._parentSelector).toBe(itemContainerSelector);
        expect(navigationHelper._itemSelector).toBe(itemSelector);
    });

    test('move down through items infinite', () => {
        const input = document.querySelector('input');
        const navigationHelper = new ArrowNavigationHelper(
            input,
            itemContainerSelector,
            itemSelector
        );

        const keydownEvent = new Event('keydown');
        keydownEvent.key = 'ArrowDown';

        for (let iteration = 0; iteration < 5; iteration++) {
            input.dispatchEvent(keydownEvent);
            expectActive(iteration % 3, navigationHelper);
        }
    });

    test('move down through items finite', () => {
        const input = document.querySelector('input');
        const navigationHelper = new ArrowNavigationHelper(
            input,
            itemContainerSelector,
            itemSelector,
            false
        );

        const keydownEvent = new Event('keydown');
        keydownEvent.key = 'ArrowDown';

        for (let iteration = 0; iteration < 5; iteration++) {
            input.dispatchEvent(keydownEvent);
            expectActive(iteration < 3 ? iteration : 2, navigationHelper);
        }
    });

    test('move up through items infinite', () => {
        const input = document.querySelector('input');
        const navigationHelper = new ArrowNavigationHelper(
            input,
            itemContainerSelector,
            itemSelector,
            true
        );

        const keydownEvent = new Event('keydown');
        keydownEvent.key = 'ArrowUp';

        for (let iteration = 0; iteration < 5; iteration++) {
            input.dispatchEvent(keydownEvent);
            expectActive(2 - (iteration % 3), navigationHelper);
        }
    });

    test('move up through items finite', () => {
        const input = document.querySelector('input');
        const navigationHelper = new ArrowNavigationHelper(
            input,
            itemContainerSelector,
            itemSelector,
            false
        );

        const keydownEvent = new Event('keydown');
        keydownEvent.key = 'ArrowUp';

        for (let iteration = 0; iteration < 5; iteration++) {
            input.dispatchEvent(keydownEvent);
            expectActive(0, navigationHelper);
        }
    });

    test('it does nothing if noting is selected', () => {
        const linkCallback = jest.fn();
        const link = document.querySelector('a');
        link.addEventListener('click', linkCallback);

        const input = document.querySelector('input');
        const navigationHelper = new ArrowNavigationHelper(
            input,
            itemContainerSelector,
            itemSelector,
            true
        );

        const event = new Event('keydown');
        event.key = 'Enter';

        input.dispatchEvent(event);
        expect(linkCallback).not.toHaveBeenCalled();
    });

    test('it does nothing if noting is selected', () => {
        const linkCallback = jest.fn();
        const link = document.querySelector('a');
        link.addEventListener('click', linkCallback);

        const input = document.querySelector('input');
        const navigationHelper = new ArrowNavigationHelper(
            input,
            itemContainerSelector,
            itemSelector,
            true
        );

        let event = new Event('keydown');
        event.key = 'ArrowDown';

        input.dispatchEvent(event);

        event = new Event('keydown');
        event.key = 'Enter';

        input.dispatchEvent(event);
        expect(linkCallback).toHaveBeenCalled();
    });

    test('it does nothing on other keys than arrows and enter', () => {
        const linkCallback = jest.fn();
        const link = document.querySelector('a');
        link.addEventListener('click', linkCallback);

        const input = document.querySelector('input');
        const navigationHelper = new ArrowNavigationHelper(
            input,
            itemContainerSelector,
            itemSelector,
            true
        );

        const event = new Event('keydown');
        event.key = 'tab';

        input.dispatchEvent(event);

        expect(navigationHelper._iterator).toBe(-1);
        expect(linkCallback).not.toBeCalled();
    });

    test('it does nothing if no items exists', () => {
        const items = document.querySelectorAll(itemSelector);
        items.forEach((node) => {
            node.remove();
        });

        const input = document.querySelector('input');
        const navigationHelper = new ArrowNavigationHelper(
            input,
            itemContainerSelector,
            itemSelector,
            true
        );

        const event = new Event('keydown');
        event.key = 'tab';

        input.dispatchEvent(event);

        expect(navigationHelper._iterator).toBe(-1);
    });

    test('it does nothing if no container exists', () => {
        const items = document.querySelectorAll(itemContainerSelector);
        items.forEach((node) => {
            node.remove();
        });

        const input = document.querySelector('input');
        const navigationHelper = new ArrowNavigationHelper(
            input,
            itemContainerSelector,
            itemSelector,
            true
        );

        const event = new Event('keydown');
        event.key = 'tab';

        input.dispatchEvent(event);

        expect(navigationHelper._iterator).toBe(-1);
    });

    function expectActive(index, navigationHelper) {
        const items = document.querySelectorAll('li');
        expect(navigationHelper._iterator).toBe(index);

        for (let i = 0; i < items.length; i++) {
            if (i === index) {
                expect(items[i].classList).toContain('is-active');
            } else {
                expect(items[i].classList).not.toContain('is-active');
            }
        }
    }
});