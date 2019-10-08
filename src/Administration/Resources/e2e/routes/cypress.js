const express = require('express');
const childProcess = require('child_process');

// ToDo: Replace with native node http server
const app = express();

app.get('/cleanup', (req, res) => {
    return childProcess.exec('./psh.phar e2e:cleanup', (err, stdout, stderr) => {
        let output = 'success';

        // ToDo: Replace with status 500 when cypress issue #5150 is released:
        //  https://github.com/cypress-io/cypress/pull/5150/files
        if (err) {
            output = err.toString() + '\n' + err.message + '\n' + stdout + '\n' + stderr;
        }

        res.send(output);
    });
});

app.listen(8005);
