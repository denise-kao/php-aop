<?php

namespace Okapi\Aop\Tests\Functional\AdviceBehavior\ModifyArgumentPassedByReference;

use Okapi\Aop\AopKernel;
use Okapi\Aop\Tests\Functional\AdviceBehavior\ModifyArgumentPassedByReference\Aspect\AddMetadataToArrayAspect;
use Okapi\Aop\Tests\Util;

class Kernel extends AopKernel
{
    protected ?string $cacheDir = Util::CACHE_DIR;

    protected array $aspects = [
        AddMetadataToArrayAspect::class,
    ];
}
