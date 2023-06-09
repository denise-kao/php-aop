<?php
/** @noinspection PhpUnused */
namespace Okapi\Aop\Tests\Functional\InterfaceAdvice\Aspect;

use Okapi\Aop\Attributes\After;
use Okapi\Aop\Attributes\Aspect;
use Okapi\Aop\Tests\Functional\InterfaceAdvice\ClassesToIntercept\UserInterface;

#[Aspect]
class UserInterfaceAspect
{
    #[After(
        class: UserInterface::class,
        method: 'getName',
    )]
    public function modifyName(): string
    {
        return 'Jane Doe';
    }
}
