<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use Shopware\Core\Framework\Test\ORM\Field\CreateAtAndUpdatedAtFieldTest;
use Shopware\Core\Framework\Test\ORM\Field\JsonFieldTest;
use Shopware\Core\Framework\Test\ORM\Field\ListFieldTest;
use Shopware\Core\Framework\Test\ORM\Field\ObjectFieldTest;
use Shopware\Core\Framework\Test\ORM\Field\WriteProtectedFieldTest;
use Shopware\Core\Framework\Test\ORM\Search\SearchCriteriaBuilderTest;
use Shopware\Storefront\Test\OrderingProcessTest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Helper class to debug data problems in the test suite
 */
class InheritanceTestListener implements TestListener
{
    private $wrongIngheritance = [
        'KernelTestCase' => [],
        'beginTransaction' => [],
        'traits' => [],
        'deletes' => [],
    ];

    private $whitelist = [
        'KernelTestCase' => [],
        'beginTransaction' => [
            CreateAtAndUpdatedAtFieldTest::class,
            JsonFieldTest::class,
            ListFieldTest::class,
            ObjectFieldTest::class,
            WriteProtectedFieldTest::class,
        ],
        'traits' => [
            OrderingProcessTest::class,
        ],
        'deletes' => [
            SearchCriteriaBuilderTest::class,
        ],
    ];

    /**
     * A test ended.
     */
    public function endTest(Test $test, float $time): void
    {
        $refl = new \ReflectionObject($test);
        $contents = file_get_contents($refl->getFileName());
        $class = get_class($test);

        if ($test instanceof KernelTestCase && !in_array($class, $this->whitelist['KernelTestCase'], true)) {
            $this->wrongIngheritance['KernelTestCase'][$refl->getFileName()] = $class;
        }

        if (strpos($contents, 'beginTransaction()') && !in_array($class, $this->whitelist['beginTransaction'], true)) {
            $this->wrongIngheritance['beginTransaction'][$refl->getFileName()] = $class;
        }

        // @todo multiple traits

        if (count($refl->getTraitNames()) > 2 && !in_array($class, $this->whitelist['traits'], true)) {
            $this->wrongIngheritance['traits'][$refl->getFileName()] = $class;
        }

        if (strpos($contents, 'DELETE FROM') && !in_array($class, $this->whitelist['deletes'], true)) {
            $this->wrongIngheritance['deletes'][$refl->getFileName()] = $class;
        }
    }

    /**
     * An error occurred.
     */
    public function addError(Test $test, \Throwable $t, float $time): void
    {
        //nth
    }

    /**
     * A warning occurred.
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
        //nth
    }

    /**
     * A failure occurred.
     */
    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        //nth
    }

    /**
     * Incomplete test.
     */
    public function addIncompleteTest(Test $test, \Throwable $t, float $time): void
    {
        //nth
    }

    /**
     * Risky test.
     */
    public function addRiskyTest(Test $test, \Throwable $t, float $time): void
    {
        //nth
    }

    /**
     * Skipped test.
     */
    public function addSkippedTest(Test $test, \Throwable $t, float $time): void
    {
        //nth
    }

    /**
     * A test suite started.
     */
    public function startTestSuite(TestSuite $suite): void
    {
        //nth
    }

    /**
     * A test suite ended.
     */
    public function endTestSuite(TestSuite $suite): void
    {
        $totalCount = count($this->wrongIngheritance['beginTransaction'])
            + count($this->wrongIngheritance['KernelTestCase'])
            + count($this->wrongIngheritance['traits'])
            + count($this->wrongIngheritance['deletes']);

        if (!$totalCount) {
            return;
        }

        echo sprintf(
            "Found %s Errors: \n",
            $totalCount
        );
        echo str_replace("\n", "\n\t", print_r($this->wrongIngheritance, true));
    }

    /**
     * A test started.
     */
    public function startTest(Test $test): void
    {
        //nth
    }
}
