const express = require('express');
const childProcess = require('child_process');

const app = express();

app.get('/cleanup', (req, res) => {
    return childProcess.exec('./psh.phar e2e:cleanup', (err, stdin, stderr) => {
        if (err) {
            console.log('stderr: ', stderr);

            const errors = err.toString() + '\n' + err.message + '\n' + stdin + '\n' + stderr;

            res.status(500).send(errors);
            return;
        }

        if (!stdin.includes('All commands successfully executed!')) {
            res.status(500).send(stdin);
            return;
        }

        res.send(stdin);
    });
});

app.listen(8005);
