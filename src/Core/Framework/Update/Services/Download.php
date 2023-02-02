<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Update\Exception\UpdateFailedException;

class Download
{
    /**
     * @var callable
     */
    private $progressCallback;

    /**
     * @var callable
     */
    private $haltCallback;

    /**
     * @throws \Exception
     */
    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    public function setHaltCallback(callable $callback): void
    {
        $this->haltCallback = $callback;
    }

    public function shouldHalt(): bool
    {
        if ($this->haltCallback === null) {
            return false;
        }

        return (bool) \call_user_func($this->haltCallback);
    }

    /**
     * @throws \Exception
     */
    public function downloadFile(string $sourceUri, string $destinationUri, int $totalSize, string $hash): int
    {
        if (($destination = fopen($destinationUri, 'a+b')) === false) {
            throw new UpdateFailedException(sprintf('Destination "%s" is invalid.', $destinationUri));
        }

        if (filesize($destinationUri) > 0) {
            throw new UpdateFailedException(sprintf('File on destination %s does already exist.', $destinationUri));
        }

        $partFile = $destinationUri . '.part';
        $partFile = new \SplFileObject($partFile, 'a+');

        $size = $partFile->getSize();
        if ($size >= $totalSize) {
            $this->verifyHash($partFile, $hash);
            // close local file connections before move for windows
            $partFilePath = $partFile->getPathname();
            fclose($destination);
            unset($partFile);
            $this->moveFile($partFilePath, $destinationUri);

            return 0;
        }

        $range = $size . '-' . ($totalSize - 1);

        if (!\function_exists('curl_init')) {
            throw new \Exception('PHP Extension "curl" is required to download a file');
        }

        // Configuration of curl
        $ch = curl_init();

        curl_setopt($ch, \CURLOPT_RANGE, $range);
        curl_setopt($ch, \CURLOPT_URL, $sourceUri);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, \CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, \CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, \CURLOPT_NOPROGRESS, false);

        // Do not remove $_ch, although it is marked as unused. It somehow important
        curl_setopt($ch, \CURLOPT_PROGRESSFUNCTION, function ($_ch, $dltotal, $dlnow) use ($size): void {
            if ($dlnow > 0) {
                $this->progress($dltotal, $dlnow, $size + $dlnow);
            }
        });

        $isHalted = false;
        $isError = false;
        curl_setopt($ch, \CURLOPT_WRITEFUNCTION, function ($ch, $str) use ($partFile, &$isHalted, &$isError) {
            if (curl_getinfo($ch, \CURLINFO_HTTP_CODE) !== 206) {
                $isError = true;

                return -1;
            }

            $writtenBytes = $partFile->fwrite($str);

            if ($this->shouldHalt()) {
                $isHalted = true;

                return -1;
            }

            return $writtenBytes;
        });

        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($isError && !$isHalted) {
            throw new \Exception('Wrong http code');
        }

        if ($result === false && !$isHalted) {
            throw new \Exception($error);
        }

        clearstatcache(false, $partFile->getPathname());
        $size = $partFile->getSize();

        if ($size >= $totalSize) {
            $this->verifyHash($partFile, $hash);
            // close local file connections before move for windows
            $partFilePath = $partFile->getPathname();
            fclose($destination);
            unset($partFile);
            $this->moveFile($partFilePath, $destinationUri);

            return $size;
        }

        // close local file

        fclose($destination);
        unset($partFile);

        return $size;
    }

    private function progress(int $downloadSize, int $downloaded, int $total): void
    {
        if ($this->progressCallback === null) {
            return;
        }

        \call_user_func_array($this->progressCallback, [$downloadSize, $downloaded, $total]);
    }

    private function verifyHash(\SplFileObject $partFile, string $hash): bool
    {
        if (sha1_file($partFile->getPathname()) !== $hash) {
            // try to delete invalid file so a valid one can be downloaded
            @unlink($partFile->getPathname());

            throw new UpdateFailedException('Hash mismatch');
        }

        return true;
    }

    private function moveFile(string $partFilePath, string $destinationUri): void
    {
        rename($partFilePath, $destinationUri);
    }
}
