const express = require('express');
const childProcess = require('child_process');

const app = express();

app.get('/cleanup', (req, res) => {
    return childProcess.exec('./psh.phar e2e:cleanup', (err, stdout, stderr) => {
        let output = 'success';

        if (err) {
            output = err.toString() + '\n' + err.message + '\n' + stdout + '\n' + stderr;
        }

        res.send(output);
    });
});

app.listen(8005);
