/**
 * @sw-package framework
 */
const DEBOUNCE_MS = 50;
const FILE_EVENTS = ['add', 'change', 'unlink'];

/**
 * Collects the Twig file events of the webpack and the Vite watcher and sends them as one update.
 *
 * Saves within the debounce window are sent as one update, so an editor that writes several files only triggers one refresh.
 * `structural` is true if a file was added or removed. A compiled template keeps the parent that it extended when it was compiled,
 * so the server then drops its compiled templates.
 *
 * @param {(update: {files: string[], structural: boolean}) => void} send
 * @returns {(event: string, file: string) => void} records a watcher event, directory events are ignored
 */
function createTwigUpdateBatcher(send) {
    let changedFiles = new Set();
    let structural = false;
    let timer = null;

    return (event, file) => {
        if (!FILE_EVENTS.includes(event)) {
            return;
        }

        changedFiles.add(file);
        structural = structural || event !== 'change';
        clearTimeout(timer);
        timer = setTimeout(() => {
            const update = { files: [...changedFiles], structural };
            changedFiles = new Set();
            structural = false;

            send(update);
        }, DEBOUNCE_MS);
    };
}

/**
 * @param {{files: string[], structural: boolean}} update
 * @returns {string}
 */
function describeTwigUpdate(update) {
    return `[sw-twig-hmr] ${update.files.join(', ')}${update.structural ? ' (added or removed)' : ''}`;
}

module.exports = { createTwigUpdateBatcher, describeTwigUpdate, DEBOUNCE_MS };
