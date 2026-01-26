// This error would break the plugin fetch because it breaks the export
throw new Error('Plugin throws an error and breaks the export');

export default class BrokenTest extends window.PluginBaseClass {
    init() {
        // This error happens internally and does not break the plugin fetching because the plugin was already fetched and initialized.
        console.log('BrokenTest init');

        throw new Error('Plugin throws an error');
    }
}
