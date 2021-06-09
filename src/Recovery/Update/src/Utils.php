<?php declare(strict_types=1);

namespace Shopware\Recovery\Update;

use Symfony\Component\Dotenv\Dotenv;

class Utils
{
    /**
     * @param string $file
     *
     * @return bool
     */
    public static function check($file)
    {
        if (file_exists($file)) {
            if (!is_writable($file)) {
                return $file;
            }

            return true;
        }

        return self::check(\dirname($file));
    }

    /**
     * @param array  $paths
     * @param string $basePath
     *
     * @return array
     */
    public static function checkPaths($paths, $basePath)
    {
        $results = [];
        foreach ($paths as $path) {
            $name = $basePath . '/' . $path;
            $result = file_exists($name) && is_readable($name) && is_writable($name);
            $results[] = [
                'name' => $path,
                'result' => $result,
            ];
        }

        return $results;
    }

    public static function getRealIpAddr()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    /**
     * @param string $dir
     * @param bool   $includeDir
     */
    public static function deleteDir($dir, $includeDir = false): void
    {
        $dir = rtrim($dir, '/') . '/';
        if (!is_dir($dir)) {
            return;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            /** @var \SplFileInfo $path */
            foreach ($iterator as $path) {
                if ($path->getFilename() === '.gitkeep') {
                    continue;
                }

                $path->isFile() ? @unlink($path->getPathname()) : @rmdir($path->getPathname());
            }
        } catch (\Exception $e) {
            // todo: add error handling
            // empty catch intendded.
        }

        if ($includeDir) {
            @rmdir($dir);
        }
    }

    /**
     * @param string $clientIp
     *
     * @return bool
     */
    public static function isAllowed($clientIp)
    {
        $allowed = trim(file_get_contents(UPDATE_PATH . '/allowed_ip.txt'));
        $allowed = explode("\n", $allowed);
        $allowed = array_map('trim', $allowed);

        return \in_array($clientIp, $allowed, true);
    }

    /**
     * @param string $lang
     *
     * @return string
     */
    public static function getLanguage($lang = null)
    {
        $allowedLanguages = ['de', 'en', 'cz', 'es', 'fr', 'it', 'nl', 'pt', 'sv'];
        $selectedLanguage = 'en';

        if ($lang && \in_array($lang, $allowedLanguages, true)) {
            return $lang;
        }

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $selectedLanguage = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $selectedLanguage = mb_substr($selectedLanguage[0], 0, 2);
        }

        if (empty($selectedLanguage) || !\in_array($selectedLanguage, $allowedLanguages, true)) {
            $selectedLanguage = 'en';
        }

        if (isset($_POST['language']) && \in_array($_POST['language'], $allowedLanguages, true)) {
            $selectedLanguage = $_POST['language'];
            $_SESSION['language'] = $selectedLanguage;
        } elseif (isset($_SESSION['language']) && \in_array($_SESSION['language'], $allowedLanguages, true)) {
            $selectedLanguage = $_SESSION['language'];
        } else {
            $_SESSION['language'] = $selectedLanguage;
        }

        return $selectedLanguage;
    }

    /**
     * @param string $shopPath
     *
     * @return \PDO
     */
    public static function getConnection($shopPath)
    {
        if (file_exists($shopPath . '/.env')) {
            (new Dotenv())
                ->usePutenv(true)
                ->load($shopPath . '/.env');
        }

        if (getenv('DATABASE_URL') && $db = parse_url(getenv('DATABASE_URL'))) {
            $db = array_map('rawurldecode', $db);
            $db['path'] = mb_substr($db['path'], 1);
            if (!isset($db['pass'])) {
                $db['pass'] = '';
            }
        } else {
            die('Critical environment variable \'DATABASE_URL\' missing!' . \PHP_EOL);
        }

        if (!isset($db['host'])) {
            $db['host'] = 'localhost';
        }

        $dsn = [];
        $dsn[] = 'host=' . $db['host'];
        $dsn[] = 'dbname=' . $db['path'];

        if (isset($db['port'])) {
            $dsn[] = 'port=' . $db['port'];
        }
        if (isset($db['unix_socket'])) {
            $dsn[] = 'unix_socket=' . $db['unix_socket'];
        }

        $dsn = 'mysql:' . implode(';', $dsn);

        $parameters = [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8MB4'"];

        if (isset($_ENV['DATABASE_SSL_CA'])) {
            $parameters[\PDO::MYSQL_ATTR_SSL_CA] = $_ENV['DATABASE_SSL_CA'];
        }

        if (isset($_ENV['DATABASE_SSL_CERT'])) {
            $parameters[\PDO::MYSQL_ATTR_SSL_CERT] = $_ENV['DATABASE_SSL_CERT'];
        }

        if (isset($_ENV['DATABASE_SSL_KEY'])) {
            $parameters[\PDO::MYSQL_ATTR_SSL_KEY] = $_ENV['DATABASE_SSL_KEY'];
        }

        if (isset($_ENV['DATABASE_SSL_DONT_VERIFY_SERVER_CERT'])) {
            $parameters[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        try {
            $conn = new \PDO(
                $dsn,
                $db['user'],
                $db['pass'],
                $parameters
            );
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            echo 'ERROR: ' . $e->getMessage();
            exit(1);
        }

        self::setNonStrictSQLMode($conn);
        self::checkSQLMode($conn);

        return $conn;
    }

    /**
     * @param string $dir
     *
     * @return array
     */
    public static function cleanPath($dir)
    {
        $errorFiles = [];

        if (is_file($dir)) {
            try {
                unlink($dir);
            } catch (\ErrorException $e) {
                $errorFiles[$dir] = true;
            }
        } else {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            /** @var \SplFileInfo $path */
            foreach ($iterator as $path) {
                try {
                    if ($path->isDir()) {
                        rmdir($path->__toString());
                    } else {
                        unlink($path->__toString());
                    }
                } catch (\ErrorException $e) {
                    $errorFiles[$dir] = true;
                }
            }

            try {
                rmdir($dir);
            } catch (\ErrorException $e) {
                $errorFiles[$dir] = true;
            }
        }

        return array_keys($errorFiles);
    }

    protected static function setNonStrictSQLMode(\PDO $conn): void
    {
        $conn->exec("SET @@session.sql_mode = ''");
    }

    /**
     * @throws \RuntimeException
     */
    private static function checkSQLMode(\PDO $conn): void
    {
        $sql = 'SELECT @@SESSION.sql_mode;';
        $result = $conn->query($sql)->fetchColumn(0);

        if (mb_strpos($result, 'STRICT_TRANS_TABLES') !== false || mb_strpos($result, 'STRICT_ALL_TABLES') !== false) {
            throw new \RuntimeException("Database error!: The MySQL strict mode is active ($result). Please consult your hosting provider to solve this problem.");
        }
    }
}
