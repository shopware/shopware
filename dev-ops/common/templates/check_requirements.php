<?php
/**
 * very small check for needed server requirements
 */

if (!version_compare(PHP_VERSION, '7.1', '>=')) {
    print 'PHP >= 7.1 is required.' . PHP_EOL;
    exit(1);
}

$db = new PDO('mysql:host=__DB_HOST__;port=__DB_PORT__;', '__DB_USER__', '__DB_PASSWORD__');

$mysqlVersion = $db->query('SELECT VERSION();')->fetchColumn();

if (!version_compare($mysqlVersion, '5.7', '>=')) {
    print 'MySQL >= 5.7 is required. Provided: ' . $mysqlVersion . PHP_EOL;
    exit(1);
}

if (stripos($mysqlVersion, 'mariadb') !== false) {
    try {
        $testValue = $db->query('SELECT JSON_VALUE("{\"bar\": \"foo\"}", "$.bar")')->fetchColumn();

        if ($testValue !== 'foo') {
            print 'Your MariaDB version does not support JSON functions. Please upgrade to at least MariaDB 10.2.';
            exit(1);
        }
    } catch (\Exception $ex) {
        print 'Your MariaDB version does not support JSON functions. Please upgrade to at least MariaDB 10.2.';
        exit(1);
    }
}

$mysqlGroupConcat = $db->query('SHOW VARIABLES LIKE "group_concat_max_len"')->fetchColumn(1);
if ($mysqlGroupConcat < 320000) {
    print 'MySQL parameter "group_concat_max_len" must be at least 320000.' . PHP_EOL;

    if (version_compare($mysqlVersion, '8.0', '>=')) {
        print 'MySQL 8 detected, setting "group_concat_max_len" to 320000 and persist.' . PHP_EOL;
        $db->query('SET PERSIST group_concat_max_len = 320000');
    } else {
        exit(1);
    }
}

print 'Requirements check: OK!' . PHP_EOL;

exit(0);
