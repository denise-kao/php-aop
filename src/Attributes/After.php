<?php

namespace Okapi\Aop\Attributes;

use Attribute;
use Okapi\Aop\Attributes\AdviceType\InterceptionAdvice;

/**
 * # After attribute
 *
 * This attribute is used to mark a method as an after advice.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class After extends InterceptionAdvice
{
}
