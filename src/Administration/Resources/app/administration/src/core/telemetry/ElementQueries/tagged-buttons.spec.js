import TaggedButtons from './tagged-buttons';

describe('src/core/telemetry/ElementQueries/tagged-button.ts', () => {
    it('recognizes added tagged button', async () => {
        let observedElements = [];

        const mo = new MutationObserver((mutations) => {
            observedElements = TaggedButtons(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const button = document.createElement('button');
        button.setAttribute('data-analytics-id', 'sw-order-detail.save');

        document.body.append(button);

        await flushPromises();

        expect(observedElements).toHaveLength(1);
        expect(observedElements[0]).toBe(button);

        mo.disconnect();
    });

    it('recognizes nested tagged button', async () => {
        let observedElements = [];

        const mo = new MutationObserver((mutations) => {
            observedElements = TaggedButtons(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const div = document.createElement('div');
        const first = document.createElement('button');
        first.setAttribute('data-analytics-id', 'sw-order-detail.save');
        const second = document.createElement('button');
        second.setAttribute('data-analytics-id', 'sw-order-detail.cancel');

        div.appendChild(first);
        div.appendChild(second);

        document.body.append(div);

        await flushPromises();

        expect(observedElements).toHaveLength(2);
        expect(observedElements).toEqual([
            first,
            second,
        ]);

        mo.disconnect();
    });

    it('does not emit buttons without data attribute', async () => {
        let observedElements = [];

        const mo = new MutationObserver((mutations) => {
            observedElements = TaggedButtons(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const div = document.createElement('div');
        const first = document.createElement('button');
        first.setAttribute('data-analytics-id', 'sw-order-detail.save');
        const second = document.createElement('button');

        div.appendChild(first);
        div.appendChild(second);

        document.body.append(div);

        await flushPromises();

        expect(observedElements).toHaveLength(1);
        expect(observedElements).toEqual([
            first,
        ]);

        mo.disconnect();
    });
});
