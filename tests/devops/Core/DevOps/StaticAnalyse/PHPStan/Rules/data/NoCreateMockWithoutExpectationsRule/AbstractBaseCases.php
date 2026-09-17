<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\TestCase;

interface BaseDependency
{
    public function value(): string;
}

/**
 * @internal
 *
 * Abstract bases are never reported on their own — the notice fires per concrete class run, so the
 * subclasses below analyse these fixtures with all call sites in view.
 */
abstract class AbstractDependencyCases extends TestCase
{
    protected BaseDependency $dependency;

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(BaseDependency::class);
    }

    protected function fetchValue(): string
    {
        return (new BaseSut($this->dependency))->run();
    }

    protected function initDependency(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
    }

    protected function buildLocalDouble(): BaseSut
    {
        $local = $this->createMock(BaseDependency::class); // FLAGGED as stub once each subclass is analysed
        $local->method('value')->willReturn('b');
        $sut = new BaseSut($local);

        return $sut;
    }
}

/**
 * @internal
 *
 * The inherited property is ->expects()-ed in one test (via the inherited helper) but bare in the other.
 * FLAGGED as mixed at the base's createMock line, naming testBare().
 */
class MixedChildCases extends AbstractDependencyCases
{
    public function testCovered(): void
    {
        $this->initDependency();
        static::assertSame('a', $this->fetchValue());
    }

    public function testBare(): void
    {
        static::assertSame('b', $this->buildLocalDouble()->run());
    }
}

/**
 * @internal
 *
 * Every test reaches the inherited ->expects()-ing helper. NOT flagged for the property — but analysing
 * this subclass still reports the base helper's local double as a pure stub.
 */
class CoveredChildCases extends AbstractDependencyCases
{
    public function testOne(): void
    {
        $this->initDependency();
        static::assertSame('a', $this->fetchValue());
    }
}

/**
 * @internal
 *
 * The runtime-notice shape this rule went blind on: the base creates the fixture in setUp() and only
 * stub-configures it in its inherited test; the subclasses chain `parent::setUp()`. The override used
 * to shadow the base setUp() out of view together with its createMock().
 */
abstract class AbstractChainedSetUpCases extends TestCase
{
    protected BaseDependency $sharedDependency;

    protected function setUp(): void
    {
        $this->sharedDependency = $this->createMock(BaseDependency::class); // FLAGGED as stub once each chaining subclass is analysed
    }

    public function testSharedValue(): void
    {
        $this->sharedDependency->method('value')->willReturn('base');

        static::assertSame('base', (new BaseSut($this->sharedDependency))->run());
    }
}

/**
 * @internal
 *
 * Chains `parent::setUp()`, so the base's shared double is live in every test here without ever being
 * ->expects()-ed. FLAGGED at the base's createMock line.
 */
class ChainedSetUpChildCases extends AbstractChainedSetUpCases
{
    private BaseDependency $ownDependency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ownDependency = $this->createMock(BaseDependency::class);
    }

    public function testOwnValue(): void
    {
        $this->ownDependency->expects($this->once())->method('value')->willReturn('own');

        static::assertSame('own', (new BaseSut($this->ownDependency))->run());
    }
}

/**
 * @internal
 *
 * Replaces setUp() without chaining: the base's shared double is never created for these tests, so its
 * createMock() must NOT be flagged through this subclass. The inherited testSharedValue() would fail at
 * runtime here — that is this fixture's concern, not the rule's.
 */
class ReplacedSetUpChildCases extends AbstractChainedSetUpCases
{
    protected function setUp(): void
    {
        $this->sharedDependency = $this->createMock(BaseDependency::class);
        $this->sharedDependency->expects($this->once())->method('value')->willReturn('replaced');
    }

    public function testReplacedValue(): void
    {
        static::assertSame('replaced', (new BaseSut($this->sharedDependency))->run());
    }
}

/**
 * @internal
 */
class BaseSut
{
    public function __construct(private readonly BaseDependency $dependency)
    {
    }

    public function run(): string
    {
        return $this->dependency->value();
    }
}
