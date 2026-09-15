/**
 * @sw-package framework
 */

import {
    blockNamesOf,
    collectMarkedBlocks,
    createBlockOverlay,
    enclosingBlockNames,
    findBlockElements,
    findMarkedElement,
    hideOverlayOnPageInteraction,
    labelPosition,
    startBlockPicking,
    unionRect,
} from './block-inspector-dom';

function render(html: string): HTMLElement {
    document.body.innerHTML = html;

    return document.body;
}

function mockRect(element: Element, rect: { top: number; left: number; width: number; height: number }): void {
    element.getBoundingClientRect = () =>
        ({
            ...rect,
            right: rect.left + rect.width,
            bottom: rect.top + rect.height,
            x: rect.left,
            y: rect.top,
            toJSON: () => rect,
        }) as DOMRect;
}

describe('adapter/view/block-inspector/block-inspector-dom', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        document.head.innerHTML = '';
    });

    describe('finding marked elements', () => {
        it('reads block names innermost first', () => {
            render('<div id="el" data-sw-block="inner outer"></div>');

            expect(blockNamesOf(document.getElementById('el')!)).toEqual([
                'inner',
                'outer',
            ]);
        });

        it('finds the closest marked ancestor of a target', () => {
            render('<div id="block" data-sw-block="a"><p><b id="leaf">x</b></p></div><i id="free"></i>');

            expect(findMarkedElement(document.getElementById('leaf'))).toBe(document.getElementById('block'));
            expect(findMarkedElement(document.getElementById('free'))).toBeNull();
            expect(findMarkedElement(null)).toBeNull();
        });

        it('lists every enclosing block from the inside out', () => {
            render(
                '<div data-sw-block="outer"><div data-sw-block="middle inner-of-middle"><span id="leaf" data-sw-block="leaf"></span></div></div>',
            );

            expect(enclosingBlockNames(document.getElementById('leaf'))).toEqual([
                'leaf',
                'middle',
                'inner-of-middle',
                'outer',
            ]);
        });

        it('groups marked elements by block name in document order', () => {
            render(
                '<p id="one" data-sw-block="a"></p><p id="two" data-sw-block="b a"></p><p id="three" data-sw-block="b"></p>',
            );

            const blocks = collectMarkedBlocks();

            expect(Array.from(blocks.keys())).toEqual([
                'a',
                'b',
            ]);
            expect(blocks.get('a')?.map((el) => el.id)).toEqual([
                'one',
                'two',
            ]);
            expect(blocks.get('b')?.map((el) => el.id)).toEqual([
                'two',
                'three',
            ]);
            expect(findBlockElements('b').map((el) => el.id)).toEqual([
                'two',
                'three',
            ]);
        });
    });

    describe('unionRect', () => {
        it('spans all elements with a box and ignores the ones without', () => {
            render('<p id="a"></p><p id="b"></p><p id="hidden"></p>');
            mockRect(document.getElementById('a')!, { top: 10, left: 20, width: 100, height: 30 });
            mockRect(document.getElementById('b')!, { top: 50, left: 5, width: 10, height: 10 });
            mockRect(document.getElementById('hidden')!, { top: 0, left: 0, width: 0, height: 0 });

            expect(unionRect(Array.from(document.querySelectorAll('p')))).toEqual({
                top: 10,
                left: 5,
                right: 120,
                bottom: 60,
            });
        });

        it('is null when nothing has a box', () => {
            render('<p id="hidden"></p>');
            mockRect(document.getElementById('hidden')!, { top: 0, left: 0, width: 0, height: 0 });

            expect(unionRect([document.getElementById('hidden')!])).toBeNull();
        });
    });

    describe('labelPosition', () => {
        const label = { width: 200, height: 20 };
        const viewport = { width: 1000, height: 800 };

        it('sits above the frame and lines up with its border', () => {
            expect(labelPosition({ top: 300, left: 120 }, label, viewport)).toEqual({
                left: 118,
                top: 280,
                inside: false,
            });
        });

        it('keeps a block that starts left of the screen on screen', () => {
            expect(labelPosition({ top: 300, left: -20 }, label, viewport).left).toBe(0);
        });

        it('shifts a label at the right edge back into the screen', () => {
            // Following the frame would put the label's right edge at 1178, past the 1000px viewport.
            expect(labelPosition({ top: 300, left: 980 }, label, viewport).left).toBe(800);
        });

        it('moves the label onto the frame when there is no room above', () => {
            expect(labelPosition({ top: 10, left: 40 }, label, viewport)).toEqual({
                left: 38,
                top: 8,
                inside: true,
            });
        });

        it('keeps a block above the screen at the top edge', () => {
            expect(labelPosition({ top: -400, left: 40 }, label, viewport)).toEqual({
                left: 38,
                top: 0,
                inside: true,
            });
        });

        it('keeps a block below the screen at the bottom edge', () => {
            expect(labelPosition({ top: 2000, left: 40 }, label, viewport).top).toBe(780);
        });

        it('falls back to the top left corner when the label is larger than the viewport', () => {
            expect(labelPosition({ top: 300, left: 40 }, { width: 1200, height: 900 }, viewport)).toEqual({
                left: 0,
                top: 0,
                inside: true,
            });
        });
    });

    describe('overlay', () => {
        it('frames the elements and labels them with the block name', () => {
            render('<div id="target"></div>');
            mockRect(document.getElementById('target')!, { top: 100, left: 40, width: 200, height: 50 });

            const overlay = createBlockOverlay();
            overlay.show('sw_block_name', [document.getElementById('target')!]);

            const frame = document.querySelector<HTMLElement>('.sw-block-inspector-overlay')!;

            expect(frame.hidden).toBe(false);
            expect(frame.style.top).toBe('100px');
            expect(frame.style.left).toBe('40px');
            expect(frame.style.width).toBe('200px');
            expect(frame.style.height).toBe('50px');
            expect(frame.textContent).toBe('{% block sw_block_name %}');
            expect(frame.classList.contains('sw-block-inspector-overlay--label-inside')).toBe(false);

            overlay.hide();

            expect(frame.hidden).toBe(true);

            overlay.destroy();

            expect(document.querySelector('.sw-block-inspector-overlay')).toBeNull();
        });

        it('moves the label inside the frame when the frame touches the top edge', () => {
            render('<div id="target"></div>');
            mockRect(document.getElementById('target')!, { top: 4, left: 0, width: 10, height: 10 });

            const overlay = createBlockOverlay();
            mockRect(document.querySelector('.sw-block-inspector-overlay__label')!, {
                top: 0,
                left: 0,
                width: 120,
                height: 20,
            });
            overlay.show('blk', [document.getElementById('target')!]);

            expect(
                document
                    .querySelector('.sw-block-inspector-overlay')!
                    .classList.contains('sw-block-inspector-overlay--label-inside'),
            ).toBe(true);

            overlay.destroy();
        });

        it('keeps the label on screen for a block that starts left of the viewport', () => {
            render('<div id="target"></div>');
            mockRect(document.getElementById('target')!, { top: 300, left: -20, width: 400, height: 80 });

            const overlay = createBlockOverlay();
            const label = document.querySelector<HTMLElement>('.sw-block-inspector-overlay__label')!;
            mockRect(label, { top: 0, left: 0, width: 240, height: 20 });
            overlay.show('blk', [document.getElementById('target')!]);

            const frame = document.querySelector<HTMLElement>('.sw-block-inspector-overlay')!;

            // The frame follows the block off screen; the label stays visible at the edge.
            expect(frame.style.left).toBe('-20px');
            expect(label.style.left).toBe('0px');
            expect(label.style.top).toBe('280px');

            overlay.destroy();
        });

        it('hides the frame when the elements lose their box', () => {
            render('<div id="target"></div>');
            const target = document.getElementById('target')!;
            mockRect(target, { top: 4, left: 0, width: 10, height: 10 });

            const overlay = createBlockOverlay();
            overlay.show('blk', [target]);
            mockRect(target, { top: 0, left: 0, width: 0, height: 0 });
            overlay.refresh();

            expect(document.querySelector<HTMLElement>('.sw-block-inspector-overlay')!.hidden).toBe(true);

            overlay.destroy();
        });
    });

    describe('hiding on page interaction', () => {
        it('hides the overlay on Escape and on a pointer press, unless blocked, until disposed', () => {
            render('<div id="target"></div>');
            const overlay = { hide: jest.fn() };
            let blocked = false;
            const dispose = hideOverlayOnPageInteraction(overlay, { unless: () => blocked });

            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));

            expect(overlay.hide).not.toHaveBeenCalled();

            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
            document.getElementById('target')!.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true }));

            expect(overlay.hide).toHaveBeenCalledTimes(2);

            blocked = true;
            document.getElementById('target')!.dispatchEvent(new MouseEvent('pointerdown', { bubbles: true }));

            expect(overlay.hide).toHaveBeenCalledTimes(2);

            blocked = false;
            dispose();
            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

            expect(overlay.hide).toHaveBeenCalledTimes(2);
        });
    });

    describe('picking', () => {
        it('reports the innermost block under the pointer and picks it on click without the page seeing the click', () => {
            render('<div data-sw-block="outer"><button id="btn" data-sw-block="inner outer">x</button></div>');
            const button = document.getElementById('btn')!;
            const pageClick = jest.fn();
            button.addEventListener('click', pageClick);

            const onHover = jest.fn();
            const onPick = jest.fn();
            const onStop = jest.fn();
            startBlockPicking({ onHover, onPick, onStop });

            button.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
            button.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));

            expect(onHover).toHaveBeenCalledTimes(1);
            expect(onHover).toHaveBeenCalledWith('inner', [button]);

            button.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

            expect(pageClick).not.toHaveBeenCalled();
            expect(onPick).toHaveBeenCalledWith('inner', button);
            expect(onStop).toHaveBeenCalledTimes(1);

            // Picking ended: later clicks reach the page again.
            button.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

            expect(pageClick).toHaveBeenCalledTimes(1);
            expect(onPick).toHaveBeenCalledTimes(1);

            button.removeEventListener('click', pageClick);
        });

        it('reports leaving all blocks and cancels on Escape', () => {
            render('<div id="block" data-sw-block="a"></div><div id="free"></div>');
            const onHover = jest.fn();
            const onPick = jest.fn();
            const onStop = jest.fn();
            startBlockPicking({ onHover, onPick, onStop });

            document.getElementById('block')!.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));
            document.getElementById('free')!.dispatchEvent(new MouseEvent('mousemove', { bubbles: true }));

            expect(onHover.mock.calls).toEqual([
                [
                    'a',
                    [document.getElementById('block')],
                ],
                [
                    null,
                    [],
                ],
            ]);

            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

            expect(onStop).toHaveBeenCalledTimes(1);

            document.getElementById('block')!.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

            expect(onPick).not.toHaveBeenCalled();
        });

        it('stops early through the returned function, once', () => {
            render('<div></div>');
            const onStop = jest.fn();
            const stop = startBlockPicking({ onHover: jest.fn(), onPick: jest.fn(), onStop });

            stop();
            stop();

            expect(onStop).toHaveBeenCalledTimes(1);
        });
    });
});
