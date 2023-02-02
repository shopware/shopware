/**
 * Directive for responsive element classes
 *
 * Usage:
 * v-responsive="{ 'is--compact': el => el.width <= 1620, timeout: 200 }"
 * Explanation:
 *  - Apply class (in this case: 'is--compact') when the width of the element is smaller than 1620px.
 *  - timeout: Sets the duration on how much the throttle should wait.
 */

Shopware.Directive.register('responsive', {
    inserted(el, binding) {
        const timeout = typeof binding.value.timeout === 'number' ? binding.value.timeout : 200;

        const handleResize = Shopware.Utils.throttle(entries => {
            entries.forEach(entry => {
                const elementSizeValues = entry.contentRect;

                Object.entries(binding.value).forEach(([breakpointClass, breakpointCallback]) => {
                    if (typeof breakpointCallback !== 'function') {
                        return;
                    }

                    if (breakpointCallback(elementSizeValues)) {
                        el.classList.add(breakpointClass);
                        return;
                    }

                    el.classList.remove(breakpointClass);
                });
            });
        }, timeout);

        const observer = new ResizeObserver(handleResize);
        observer.observe(el);
    },
});
