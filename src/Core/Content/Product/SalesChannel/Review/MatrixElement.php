<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

if (class_exists('Shopware\Storefront\Page\Product\Review\MatrixElement')) {
    /**
     * @deprecated tag:v6.5.0 - reason:class-hierarchy-change - won't extend the storefront class anymore
     */
    class MatrixElement extends \Shopware\Storefront\Page\Product\Review\MatrixElement
    {
        /**
         * @var int
         */
        protected $points;

        /**
         * @var int
         */
        protected $count;

        /**
         * @var float
         */
        protected $percent;

        public function __construct(int $points, int $count, float $percent = 0.0)
        {
            $this->points = $points;
            $this->count = $count;
            $this->percent = $percent;
        }

        public function getPoints(): int
        {
            return $this->points;
        }

        public function setPoints(int $points): MatrixElement
        {
            $this->points = $points;

            return $this;
        }

        public function getCount(): int
        {
            return $this->count;
        }

        public function setCount(int $count): MatrixElement
        {
            $this->count = $count;

            return $this;
        }

        public function getPercent(): float
        {
            return $this->percent;
        }

        public function setPercent(float $percent): MatrixElement
        {
            $this->percent = $percent;

            return $this;
        }
    }
} else {
    class MatrixElement
    {
        /**
         * @var int
         */
        protected $points;

        /**
         * @var int
         */
        protected $count;

        /**
         * @var float
         */
        protected $percent;

        public function __construct(int $points, int $count, float $percent = 0.0)
        {
            $this->points = $points;
            $this->count = $count;
            $this->percent = $percent;
        }

        public function getPoints(): int
        {
            return $this->points;
        }

        public function setPoints(int $points): MatrixElement
        {
            $this->points = $points;

            return $this;
        }

        public function getCount(): int
        {
            return $this->count;
        }

        public function setCount(int $count): MatrixElement
        {
            $this->count = $count;

            return $this;
        }

        public function getPercent(): float
        {
            return $this->percent;
        }

        public function setPercent(float $percent): MatrixElement
        {
            $this->percent = $percent;

            return $this;
        }
    }
}
