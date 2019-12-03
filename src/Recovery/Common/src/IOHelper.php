<?php declare(strict_types=1);

namespace Shopware\Recovery\Common;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class IOHelper
{
    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var QuestionHelper
     */
    private $questionHelper;

    public function __construct(
        InputInterface $input,
        OutputInterface $output,
        QuestionHelper $questionHelper
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->questionHelper = $questionHelper;
    }

    /**
     * Returns true if the input is interactive
     *
     * @return bool
     */
    public function isInteractive()
    {
        return $this->input->isInteractive();
    }

    /**
     * Returns true if output is quiet
     *
     * @return bool
     */
    public function isQuiet()
    {
        return $this->output->getVerbosity() === OutputInterface::VERBOSITY_QUIET;
    }

    /**
     * Return true if output is verbose (or more verbose)
     *
     * @return bool
     */
    public function isVerbose()
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }

    /**
     * Return true if output is ver verbose (or debug)
     *
     * @return bool
     */
    public function isVeryVerbose()
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE;
    }

    /**
     * Return true if output is debug
     *
     * @return bool
     */
    public function isDebug()
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;
    }

    /**
     * Write a message to STDOUT without trailing newline
     *
     * @param string $message
     */
    public function write($message)
    {
        $this->output->write($message);
    }

    /**
     * Write a message to STDOUT with trailing newline
     *
     * @param string $message
     */
    public function writeln($message)
    {
        $this->output->write($message, true);
    }

    /**
     * Ask a $question
     *
     * @param string|Question $question
     *
     * @return string|null
     */
    public function ask($question, $default = null)
    {
        $question = $question instanceof Question ? $question : new Question($question, $default);

        return $this->questionHelper->ask($this->input, $this->output, $question);
    }

    /**
     * Ask for confirmation
     *
     * @param string|Question $question
     *
     * @return string
     */
    public function askConfirmation($question, bool $default = true)
    {
        $question = $question instanceof ConfirmationQuestion
            ? $question
            : new ConfirmationQuestion($question, $default);

        return $this->questionHelper->ask($this->input, $this->output, $question);
    }

    /**
     * Ask a question and validate the result
     *
     * @param string|Question $question
     * @param bool|callable   $validator
     * @param bool|int        $attempts
     * @param string|null     $default
     *
     * @return string
     */
    public function askAndValidate($question, $validator = false, $attempts = false, $default = null)
    {
        $question = $question instanceof Question ? $question : new Question($question, $default);

        if ($attempts) {
            $question->setMaxAttempts($attempts);
        }

        if ($validator) {
            $question->setValidator($validator);
        }

        return $this->questionHelper->ask($this->input, $this->output, $question);
    }

    /**
     * @return string
     */
    public function askMultiLineQuestion(Question $question)
    {
        $line = $this->ask($question);

        $lines = [];
        if (!empty($line)) {
            $lines[] = $line;
        }

        while (!empty($line) || empty($lines)) {
            $line = $this->ask(new Question(''));
            if (!empty($line)) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param int $max Maximum steps (0 if unknown)
     *
     * @return ProgressBar
     */
    public function createProgressBar($max = 0)
    {
        return new ProgressBar($this->output, $max);
    }

    /**
     * Clear screen
     */
    public function cls()
    {
        if (!$this->input->isInteractive()) {
            return;
        }

        // http://en.wikipedia.org/wiki/ANSI_escape_code
        $this->output->write(chr(27) . '[2J'); // ED – Erase Display
        $this->output->write(chr(27) . '[1;1H'); // CUP – Set Cursor Position to upper left
    }

    /**
     * Prints banner to output interface
     */
    public function printBanner()
    {
        $this->output->writeln($this->getBanner());
    }

    /**
     * @return string
     */
    private function getBanner()
    {
        $banner = <<<EOT
     _
 ___| |__   ___  _ ____      ____ _ _ __ ___
/ __| '_ \ / _ \| '_ \ \ /\ / / _` | '__/ _ \
\__ \ | | | (_) | |_) \ V  V / (_| | | |  __/
|___/_| |_|\___/| .__/ \_/\_/ \__,_|_|  \___|
                |_|
EOT;

        return $banner;
    }
}
