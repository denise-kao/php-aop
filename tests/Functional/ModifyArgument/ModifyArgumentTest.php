<?php

namespace Okapi\Aop\Tests\Functional\ModifyArgument;

use Okapi\Aop\Tests\Functional\ModifyArgument\Aspect\NumberHelperAspect;
use Okapi\Aop\Tests\Functional\ModifyArgument\ClassesToIntercept\NumberHelper;
use Okapi\Aop\Tests\Stubs\Kernel\ApplicationKernel;
use Okapi\Aop\Tests\Util;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
class ModifyArgumentTest extends TestCase
{
    /**
     * @see NumberHelperAspect::removeNegativeNumbers()
     */
    public function testModifyArgument(): void
    {
        Util::clearCache();
        ApplicationKernel::init();

        $numberHelper = new NumberHelper();

        $numbers = [1, 2, 3, 4, 5];
        $expected = 15;
        $actual = $numberHelper->sumArray($numbers);
        $this->assertEquals($expected, $actual);

        $numbers = [1, 2, -3, 4, 5];
        $expected = 12;
        $actual = $numberHelper->sumArray($numbers);
        $this->assertEquals($expected, $actual);
    }
}
