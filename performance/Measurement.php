<?php declare(strict_types=1);

class Measurement
{
    private $start;

    private $maxRows;

    public function start(int $maxRows)
    {
        $this->start = microtime(true);
        $this->maxRows = $maxRows;
    }

    public function tick(int $current): string
    {
        $rawIn = microtime(true) - $this->start;
        $in = $this->format($rawIn);
        $percentage = number_format(($current/$this->maxRows) * 100, 2);
        $per = round((($current * 100000) / round($rawIn * 100000)));

        return "\t$current/{$this->maxRows} \t$percentage% \tin \t$in \t ø{$per} per Sec";
    }

    public function finish(): string
    {
        $rawIn = microtime(true) - $this->start;
        $in = $this->format($rawIn);
        $percentage = 100.00;
        $per = round((($this->maxRows * 100000) / round($rawIn * 100000)));

        return "\t{$this->maxRows}/{$this->maxRows} \t$percentage% \tin \t$in \t ø{$per} per Sec";
    }

    private function format(float $in) {
        if($in < 60) {
            return number_format($in, 2) . ' Sec';
        }

        return number_format($in / 60, 4) . ' Min';
    }
}