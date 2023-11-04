const decoder = new TextDecoder();

async function tailLog(response, element) {
    const reader = response.body.getReader();

    while (true) {
        const {value, done} = await reader.read();
        if (done) break;

        const text = decoder.decode(value);

        let result = null

        try {
            let strings = text.split("\n");
            result = JSON.parse(strings.pop());

            element.innerHTML += strings.join("\n") + "\n";
            element.scrollTop = element.scrollHeight;
        } catch (e) {
            element.innerHTML += text;
            element.scrollTop = element.scrollHeight;
        }

        if (result) {
            if (!result.success) {
                throw new Error('update failed');
            }

            return result
        }
    }

    throw new Error('Unexpected end of stream');
}

const installButton = document.getElementById('install-start');
const updateButton = document.getElementById('update-start');
const logCard = document.getElementById('log-card');
const logOutput = document.getElementById('log-output');
const logError = document.getElementById('log-error');

if (installButton) {
    installButton.onclick = async function () {
        logCard.style.removeProperty('display');

        installButton.disabled = true;

        const shopwareVersion = document.getElementById('shopwareVersion');

        const installResponse = await fetch(`${baseUrl}/install/_run?shopwareVersion=` + shopwareVersion.value, {method: 'POST'});

        try {
            const result = await tailLog(installResponse, logOutput);
            if (result.newLocation) {
                // Delete installer
                await fetch(`${baseUrl}/install/_cleanup`, {method: 'POST'})

                window.location = result.newLocation;
            }
        } catch (e) {
            console.log(e);
            return showLog();
        }
    }
}

if (updateButton) {
    updateButton.onclick = async function () {
        updateButton.disabled = true;
        logCard.style.removeProperty('display');

        const shopwareVersion = document.getElementById('shopwareVersion');

        const prepareUpdate = await fetch(`${baseUrl}/update/_prepare`, {method: 'POST'})
        if (prepareUpdate.status !== 200) {
            logOutput.innerHTML += 'Failed to prepare update' + "\n"
            return showLog();
        } else {
            try {
                await tailLog(prepareUpdate, logOutput);
            } catch (e) {
                return showLog();
            }
        }

        if (!isFlexProject) {
            logOutput.innerHTML += 'Updating to Flex Project' + "\n"

            const migrate = await fetch(`${baseUrl}/update/_migrate-template`, {method: 'POST'})

            if (migrate.status !== 204 && migrate.status !== 200) {
                logOutput.innerHTML += 'Failed to update to Flex Project' + "\n"
                logOutput.innerHTML += await migrate.text();
                return showLog();
            } else {
                logOutput.innerHTML += 'Updated to Flex Project' + "\n"
            }
        }

        const updateRun = await fetch(`${baseUrl}/update/_run?shopwareVersion=${shopwareVersion.value}`, {method: 'POST'});

        try {
            await tailLog(updateRun, logOutput);
        } catch (e) {
            return showLog();
        }

        const resetConfig = await fetch(`${baseUrl}/update/_reset_config`, {method: 'POST'});

        if (resetConfig.status !== 200) {
            logOutput.innerHTML += 'Failed to update config files' + "\n"
            logOutput.innerHTML += await resetConfig.text();
        } else {
            try {
                await tailLog(resetConfig, logOutput);
            } catch (e) {
                return showLog();
            }
        }

        const finishUpdate = await fetch(`${baseUrl}/update/_finish`, {method: 'POST'})
        if (finishUpdate.status !== 200) {
            logOutput.innerHTML += 'Failed to prepare update' + "\n"
            logOutput.innerHTML += await finishUpdate.text();
        } else {
            try {
                await tailLog(finishUpdate, logOutput);
            } catch (e) {
                return showLog();
            }
        }

        window.location.href = `${baseUrl}/finish`;
    }
}

function showLog() {
    logError.style.removeProperty('display');
}

const downloadLogButton = document.getElementById('download-log');

if (downloadLogButton) {
    downloadLogButton.onclick = function () {
        const text = new Blob([logOutput.innerText], {type: 'text/plain'});
        const element = document.createElement('a');
        element.href = URL.createObjectURL(text);
        element.setAttribute('download', 'log.txt');

        element.style.display = 'none';
        document.body.appendChild(element);

        element.click();

        document.body.removeChild(element);
    }
}
