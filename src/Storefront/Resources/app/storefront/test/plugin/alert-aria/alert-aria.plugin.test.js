import AlertAriaPlugin from 'src/plugin/alert-aria/alert-aria.plugin';

describe('src/plugin/alert-aria/alert-aria.plugin', () => {
    const infoAlertTemplate = `
        <div class="alert alert-info" role="alert" aria-live="polite">
            <div class="alert-content-container">
                An info message on initial page load
            </div>
        </div>
    `;

    const dangerAlertTemplate = `
        <div class="alert alert-danger" role="alert" aria-live="assertive">
            <div class="alert-content-container">
                An error message on initial page load
            </div>
        </div>
    `;

    const invalidAlertTemplate = `
        <div class="alert alert-info" role="alert" aria-live="polite">
            An invalid alert without container
        </div>
    `;

    const missingAriaTemplate = `
        <div class="alert alert-danger" role="alert">
            <div class="alert-content-container">
                Alert with missing aria-live attribute
            </div>
        </div>
    `;

    function initPlugin(template, options = {}) {
        document.body.innerHTML = template;
        return new AlertAriaPlugin(document.querySelector('.alert'), options);
    }

    beforeEach(() => {
        document.body.innerHTML = '';
    });

    test('plugin initializes', () => {
        const plugin = initPlugin(infoAlertTemplate);

        expect(typeof plugin).toBe('object');
        expect(plugin).toBeInstanceOf(AlertAriaPlugin);
    });

    test('announces info alert by changing aria-hidden attribute after timeout', () => {
        jest.useFakeTimers();

        initPlugin(infoAlertTemplate);

        // Ensure aria-hidden is set to true initially.
        expect(document.querySelector('.alert-content-container').getAttribute('aria-hidden')).toBe('true');

        jest.advanceTimersByTime(1500);

        // Ensure aria-hidden update after timeout.
        expect(document.querySelector('.alert-content-container').getAttribute('aria-hidden')).toBe('false');

        jest.useRealTimers();
    });

    test('announces assertive alert by changing aria-hidden attribute after timeout', () => {
        jest.useFakeTimers();

        initPlugin(dangerAlertTemplate, { ariaLive: 'assertive' });

        // Ensure aria-hidden is set to true initially.
        expect(document.querySelector('.alert-content-container').getAttribute('aria-hidden')).toBe('true');

        jest.advanceTimersByTime(1000);

        // Ensure aria-hidden update after timeout.
        expect(document.querySelector('.alert-content-container').getAttribute('aria-hidden')).toBe('false');

        jest.useRealTimers();
    });

    test('handles invalid alert template without content container', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        initPlugin(invalidAlertTemplate);

        expect(consoleSpy).toHaveBeenCalledWith('[AlertAriaPlugin] The alert content container cannot be found.');
        consoleSpy.mockRestore();
    });

    test('handles missing aria-live attribute', () => {
        const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();

        initPlugin(missingAriaTemplate);

        expect(consoleSpy).toHaveBeenCalledWith('[AlertAriaPlugin] The "aria-live" attribute is not found on the alert. The alert will not be announced.');
        consoleSpy.mockRestore();
    });
});
