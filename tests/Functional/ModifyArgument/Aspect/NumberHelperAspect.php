<?php
/** @noinspection PhpUnused */
namespace Okapi\Aop\Tests\Functional\ModifyArgument\Aspect;

use Okapi\Aop\Attributes\Aspect;
use Okapi\Aop\Attributes\Before;
use Okapi\Aop\Invocation\BeforeMethodInvocation;
use Okapi\Aop\Tests\Functional\ModifyArgument\ClassesToIntercept\NumberHelper;

#[Aspect]
class NumberHelperAspect
{
    #[Before(
        class: NumberHelper::class,
        method: 'sumArray',
    )]
    public function removeNegativeNumbers(BeforeMethodInvocation $invocation): void
    {
        $numbers = $invocation->getArgument(0);
        $numbers = array_filter($numbers, fn($number) => $number >= 0);
        $invocation->setArgument(0, $numbers);
    }
}
