import ProductAnalyticsTag from './product-analytics-tag';

describe('src/core/telemetry/ElementQueries/product-analytics-tag.ts', () => {
    it('recognizes added elements with product analytics tag', async () => {
        let observedElements = [];

        const mo = new MutationObserver((mutations) => {
            observedElements = ProductAnalyticsTag(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const taggedElement = document.createElement('div');
        taggedElement.setAttribute('data-product-analytics', true);

        document.body.append(taggedElement);

        await flushPromises();

        expect(observedElements).toHaveLength(1);
        expect(observedElements[0]).toBe(taggedElement);

        mo.disconnect();
    });

    it('recognizes nested elements with product analytics tag', async () => {
        let observedElements = [];

        const mo = new MutationObserver((mutations) => {
            observedElements = ProductAnalyticsTag(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const div = document.createElement('div');
        const first = document.createElement('span');
        first.setAttribute('data-product-analytics', true);
        const second = document.createElement('button');
        second.setAttribute('data-product-analytics', true);

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
            observedElements = ProductAnalyticsTag(mutations);
        });

        mo.observe(document, {
            childList: true,
            subtree: true,
        });

        const div = document.createElement('div');
        const first = document.createElement('button');
        first.setAttribute('data-product-analytics', true);
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
