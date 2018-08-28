const chalk = require('chalk');

//This plugin exits the node process on an unsuccessful webpack build to fail the tests
class KillProcessOnFailedBuildPlugin {
    apply(compiler) {
        compiler.hooks.done.tap('done', (stats) => {
            if (this.buildSuccessful(stats)) {
                return;
            }

            chalk.enabled = true;
            chalk.level = 1;

            this.printItem('==== Webpack build failed! ====');

            // Log each of the warnings
            this.printMessages(stats.compilation.warnings, chalk.yellow);

            // Log each of the errors
            this.printMessages(stats.compilation.errors);

            this.printItem('===============================');

            process.exit(1);
        });
    }

    buildSuccessful(stats) {
        return (stats.compilation.errors.length === 0) && (stats.compilation.warnings.length === 0);
    }

    printMessages(messages, style=chalk.red) {
        messages.forEach((msg) => {
            const message = msg.message || String(msg);
            this.printItem(message, style);
        })
    }

    printItem(item, style=chalk.red) {
        console.log(style(item));
    }
}

module.exports = KillProcessOnFailedBuildPlugin;
