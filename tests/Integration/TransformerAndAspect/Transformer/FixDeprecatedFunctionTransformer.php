<?php

namespace Okapi\Aop\Tests\Integration\TransformerAndAspect\Transformer;

use Microsoft\PhpParser\Node\QualifiedName;
use Okapi\Aop\Tests\Integration\TransformerAndAspect\Target\DeprecatedAndWrongClass;
use Okapi\CodeTransformer\Transformer;
use Okapi\CodeTransformer\Transformer\Code;

class FixDeprecatedFunctionTransformer extends Transformer
{
    public function getTargetClass(): string|array
    {
        return DeprecatedAndWrongClass::class;
    }

    public function transform(Code $code): void
    {
        $sourceFileNode = $code->getSourceFileNode();

        foreach ($sourceFileNode->getDescendantNodes() as $node) {
            if ($node instanceof QualifiedName && $node->getText() === 'is_real') {
                $code->edit(
                    $node->nameParts[0],
                    'is_float',
                );
            }
        }
    }
}
