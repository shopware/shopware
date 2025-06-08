import RemoteClickPlugin from 'src/plugin/remote-click/remote-click.plugin';

describe('RemoteClickPlugin tests', () => {

    beforeEach(() => {
        window.scrollTo = jest.fn();
        global.alert = jest.fn();

        document.body.innerHTML = `
            <button data-remote-click="true">Show stuff</button>

            <button class="remote-click-target" onclick="alert('I was clicked remotely!')">
                I will be clicked remotely
            </button>
        `;

        window.getComputedStyle = jest.fn(() => {
            return {
                getPropertyValue: jest.fn(() => {
                    return 'xs';
                }),
            };
        });
    });

    test('is able to click an element remotely', () => {
        const element = document.querySelector('[data-remote-click]');

        const instance = new RemoteClickPlugin(element, {
            selector: '.remote-click-target',
        });
        instance.$emitter.publish = jest.fn();

        element.dispatchEvent(new Event('click', { bubbles: true }));

        expect(window.scrollTo).toHaveBeenCalled();
        expect(global.alert).toHaveBeenCalledWith(
            expect.stringContaining('I was clicked remotely!')
        );
        expect(instance.$emitter.publish).toHaveBeenCalledWith('onClick');
    });

    test('should not execute remote click if not allowed in current viewport', () => {
        const element = document.querySelector('[data-remote-click]');

        const instance = new RemoteClickPlugin(element, {
            selector: '.remote-click-target',
            excludedViewports: ['XS'], // Exclude the current viewport
        });
        instance.$emitter.publish = jest.fn();

        element.dispatchEvent(new Event('click', { bubbles: true }));

        // Events should not be triggered because viewport is not allowed
        expect(window.scrollTo).not.toHaveBeenCalled();
        expect(global.alert).not.toHaveBeenCalled();
        expect(instance.$emitter.publish).not.toHaveBeenCalled();
    });

    test('should throw an error when no selector is given as an option', () => {
        const element = document.querySelector('[data-remote-click]');

        expect(() => {
            new RemoteClickPlugin(element);
        }).toThrowError('The option "selector" must be given!');
    });
});