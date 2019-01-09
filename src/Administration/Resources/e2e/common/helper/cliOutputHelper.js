export default class CliOutputHelper {
    createCliEntry(message, type) {
        if (type === 'success') {
            console.log(`• ✓ - ${message}`);
            console.log();
        } else if (type === 'error') {
            console.log(`• ✖ - ${message}`);
            console.log();
        } else if (type === 'title') {
            console.log();
            console.log(`### ${message}`);
        }
        else {
            console.log(message)
        }
    }
}