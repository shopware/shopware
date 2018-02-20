<?php

$defaults = [
    'APP_SECRET' => '10cf4884eaee9e8ed0fbcfa3f9795da0',
    'APP_ENV' => 'dev',
    'APP_DEBUG' => '1',
    'DATABASE_HOST' => '',
    'DATABASE_PORT' => '3306',
    'DATABASE_NAME' => '',
    'DATABASE_USER' => '',
    'DATABASE_PASSWORD' => '',
    'DATABASE_CHARSET' => 'utf8mb4',
    'MAILER_TRANSPORT' => '',
    'MAILER_HOST' => '',
    'MAILER_USER' => '',
    'MAILER_PASSWORD' => '',
];

$_POST = array_merge($defaults, $_POST);

function install() {
    try {
        $connection = new PDO(sprintf('mysql:host=%s;dbname=%s', $_POST['DATABASE_HOST'], $_POST['DATABASE_NAME']), $_POST['DATABASE_USER'], $_POST['DATABASE_PASSWORD']);
    } catch(PDOException $ex){
        print '<p style="color:red">Database credentials are not valid.</p>';
        return;
    }

    $data = [];
    foreach ($_POST as $k => $v) {
        $data[] = $k . '=' . $v;
    }

    file_put_contents(__DIR__ . '/../.env', implode(PHP_EOL, $data));

    $templine = '';
    $lines = file(__DIR__ . '/../install.sql');
    foreach ($lines as $line) {
        if (0 === strpos($line, '--') || $line === '') {
            continue;
        }

        $templine .= $line;
        if (substr(trim($line), -1, 1) === ';') {
            $connection->exec($templine);
            $templine = '';
        }
    }

    $protocol = $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
    $host = $_SERVER['SERVER_NAME'];
    $path = str_replace('/install.php', '', $_SERVER['SCRIPT_NAME']);

    $connection->prepare(sprintf('UPDATE shop SET host = "%s", base_url = "%s"', $host, $path))->execute();

    $shopUrl = $protocol . '://' . $host . $path;
    $storefront = $shopUrl;
    $administration = $shopUrl . '/admin';

    print sprintf('<p style="color:green">Installed successfully. <a href="%s">Go to storefront</a>. <a href="%s">Go to administration</a>.', $storefront, $administration);

    unlink(__DIR__ . '/install.php');
}

// install on POST
if (!empty(array_diff($_POST, $defaults))) {
    install();
}

?>
<style>
    body {width: 768px;margin: 20px auto}
    td {padding: 10px}
</style>
<h1>Welcome to the new shiny Shopware Platform installer</h1>

<form action="install.php" method="post">
    <table>
        <tr>
            <th colspan="2">Database</th>
        </tr>
        <tr>
            <td>Host</td>
            <td><input type="text" name="DATABASE_HOST" value="<?php echo $_POST['DATABASE_HOST'] ?? $_ENV['DATABASE_HOST'] ?? 'localhost'; ?>" /></td>
        </tr>
        <tr>
            <td>Port</td>
            <td><input type="text" name="DATABASE_PORT" value="<?php echo $_POST['DATABASE_PORT'] ?? $_ENV['DATABASE_PORT'] ?? 3306; ?>" /></td>
        </tr>
        <tr>
            <td>User</td>
            <td><input type="text" name="DATABASE_USER" value="<?php echo $_POST['DATABASE_USER'] ?? $_ENV['DATABASE_USER'] ?? 'shopware'; ?>" /></td>
        </tr>
        <tr>
            <td>Password</td>
            <td><input type="password" name="DATABASE_PASSWORD" value="<?php echo $_POST['DATABASE_PASSWORD'] ?? $_ENV['DATABASE_PASSWORD'] ?? 'shopware'; ?>" /></td>
        </tr>
        <tr>
            <td>DB-Name</td>
            <td><input type="text" name="DATABASE_NAME" value="<?php echo $_POST['DATABASE_NAME'] ?? $_ENV['DATABASE_NAME'] ?? 'next'; ?>" /></td>
        </tr>
    </table>

    <table>
        <tr>
            <th colspan="2">Application</th>
        </tr>
        <tr>
            <td>Environment</td>
            <td>
                <select name="APP_ENV">
                    <option value="dev" <?php echo ($_POST['APP_ENV'] === 'dev') ? 'selected' : ''; ?>>Development</option>
                    <option value="prod" <?php echo ($_POST['APP_ENV'] === 'prod') ? 'selected' : ''; ?>>Production</option>
                </select>
            </td>
        </tr>
        <tr>
            <td>Debug</td>
            <td>
                <select name="APP_DEBUG">
                    <option value="1" <?php echo $_POST['APP_DEBUG'] === '1' ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo $_POST['APP_DEBUG'] === '0' ? 'selected' : ''; ?>>No</option>
                </select>
            </td>
        </tr>
    </table>

    <input type="submit" />
</form>

