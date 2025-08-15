<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);
namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 *
 * @experimental stableVersion:v6.8.0 feature:FLOW_EXECUTION_AFTER_BUSINESS_PROCESS
 */
#[Package('after-sales')]
class FlowExecutionDepthProvider {
    private int $flowExecutionDepth = 0;

    public function getFlowExecutionDepth(): int {
        return $this->flowExecutionDepth;
    }

    public function setFlowExecutionDepth(int $flowExecutionDepth): void
    {
        $this->flowExecutionDepth = $flowExecutionDepth;

    }
}
