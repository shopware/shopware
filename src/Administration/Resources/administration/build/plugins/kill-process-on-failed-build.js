const chalk = require('chalk');

// This plugin exits the node process on an unsuccessful webpack build to fail the tests
class KillProcessOnFailedBuildPlugin {
    static apply(compiler) {
        compiler.hooks.done.tap('done', (stats) => {
            if (KillProcessOnFailedBuildPlugin.buildSuccessful(stats)) {
                return;
            }

            chalk.enabled = true;
            chalk.level = 1;

            KillProcessOnFailedBuildPlugin.printItem('==== Webpack build failed! ====');

            // Log each of the warnings
            KillProcessOnFailedBuildPlugin.printMessages(stats.compilation.warnings, chalk.yellow);

            // Log each of the errors
            KillProcessOnFailedBuildPlugin.printMessages(stats.compilation.errors);

            KillProcessOnFailedBuildPlugin.printItem('===============================');

            process.exit(1);
        });
    }

    static buildSuccessful(stats) {
        return (stats.compilation.errors.length === 0) && (stats.compilation.warnings.length === 0);
    }

    static printMessages(messages, style = chalk.red) {
        messages.forEach((msg) => {
            const message = msg.message || String(msg);
            KillProcessOnFailedBuildPlugin.printItem(message, style);
        });
    }

    static printItem(item, style = chalk.red) {
        console.log(style(item));
    }
}

module.exports = KillProcessOnFailedBuildPlugin;
