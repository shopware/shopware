<?php declare(strict_types=1);

namespace Shopware\Recovery\Update\Steps;

use Shopware\Recovery\Common\DumpIterator;
use Shopware\Recovery\Common\Steps\ErrorResult;
use Shopware\Recovery\Common\Steps\FinishResult;
use Shopware\Recovery\Common\Steps\ValidResult;

class SnippetStep
{
    private $conn;

    private $dumper;

    public function __construct(\PDO $connection, DumpIterator $dumper)
    {
        $this->conn = $connection;
        $this->dumper = $dumper;
    }

    public function run($offset)
    {
        $conn = $this->conn;
        $dump = $this->dumper;

        $totalCount = $dump->count();

        $preSql = '
        SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
        SET time_zone = "+00:00";
        SET @locale_de_DE = (SELECT id FROM s_core_locales WHERE locale = "de_DE");
        SET @locale_en_GB = (SELECT id FROM s_core_locales WHERE locale = "en_GB");
        ';
        $conn->exec($preSql);
        $dump->seek($offset);

        $startTime = microtime(true);
        $sql = [];
        $count = 0;
        while ($dump->valid() && ++$count) {
            $current = trim($dump->current());
            if (empty($current)) {
                $dump->next();

                continue;
            }

            $sql[] = $current;

            if ($count % 50 === 0) {
                try {
                    $conn->exec(implode(";\n", $sql));
                    $sql = [];
                } catch (\PDOException $e) {
                    return new ErrorResult($e->getMessage(), $e, ['query' => $sql]);
                }
            }

            $dump->next();
            if ($count > 5000 || ceil(microtime(true) - $startTime) > 5) {
                break;
            }
        }

        if (!empty($sql)) {
            try {
                $conn->exec(implode(";\n", $sql));
            } catch (\PDOException $e) {
                return new ErrorResult('second' . $e->getMessage(), $e, ['query' => $sql]);
            }
        }

        if ($dump->valid()) {
            return new ValidResult($dump->key(), $totalCount);
        }

        return new FinishResult($dump->key(), $totalCount);
    }
}
