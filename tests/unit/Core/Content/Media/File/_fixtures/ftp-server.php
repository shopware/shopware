<?php declare(strict_types=1);

[, $file, $pasvAddress, $epsv, $stall] = $_SERVER['argv'] + [4 => '0'];

$control = stream_socket_server('tcp://127.0.0.1:0');
$data = stream_socket_server('tcp://127.0.0.1:0');
if ($control === false || $data === false) {
    exit(1);
}

$dataPort = (int) substr((string) strrchr((string) stream_socket_get_name($data, false), ':'), 1);
echo substr((string) strrchr((string) stream_socket_get_name($control, false), ':'), 1), \PHP_EOL;

while ($client = @stream_socket_accept($control, -1)) {
    fwrite($client, "220 ready\r\n");

    while (($line = fgets($client)) !== false) {
        $command = strtoupper(substr(trim($line), 0, 4));

        $reply = match ($command) {
            'USER' => '331 password required',
            'PASS' => '230 logged in',
            'PWD' => '257 "/"',
            'TYPE' => '200 ok',
            'CWD' => '250 ok',
            'SIZE' => '213 ' . filesize($file),
            'EPSV' => $epsv === '1' ? '229 passive (|||' . $dataPort . '|)' : '500 not supported',
            'PASV' => '227 passive (' . str_replace('.', ',', $pasvAddress) . ',' . intdiv($dataPort, 256) . ',' . ($dataPort % 256) . ')',
            'RETR' => '150 opening',
            'QUIT' => '221 bye',
            default => '502 not implemented',
        };

        fwrite($client, $reply . "\r\n");

        if ($command === 'RETR') {
            $connection = stream_socket_accept($data, 5);
            if ($connection !== false) {
                fwrite($connection, (string) file_get_contents($file, length: $stall === '1' ? 1 : null));
                if ($stall === '1') {
                    sleep(30);
                }
                fclose($connection);
            }
            fwrite($client, "226 done\r\n");
        }

        if ($command === 'QUIT') {
            break;
        }
    }

    fclose($client);
}
