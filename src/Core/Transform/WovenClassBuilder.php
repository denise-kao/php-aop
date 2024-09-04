<?php
/** @noinspection PhpPropertyOnlyWrittenInspection */
namespace Okapi\Aop\Core\Transform;

use DI\Attribute\Inject;
use Nette\PhpGenerator\ClassLike;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PromotedParameter;
use Nette\PhpGenerator\Property;
use Okapi\Aop\Core\Cache\CachePaths;
use Okapi\Aop\Core\Container\AdviceContainer;
use Okapi\Aop\Core\Container\AdviceType\MethodAdviceContainer;
use Okapi\Aop\Core\JoinPoint\JoinPoint;
use Okapi\Aop\Core\JoinPoint\JoinPointInjector;
use Okapi\CodeTransformer\Core\DI;
use Okapi\CodeTransformer\Transformer\Code;
use Roave\BetterReflection\Reflection\Adapter\ReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionMethod as BetterReflectionMethod;

/**
 * # Woven Class Builder
 *
 * This class is used to build woven classes.
 */
class WovenClassBuilder
{
    // region DI

    #[Inject]
    private CachePaths $cachePaths;

    // endregion

    /**
     * WeavingClassBuilder constructor.
     *
     * @param Code              $code
     * @param AdviceContainer[] $adviceContainers
     */
    public function __construct(
        private readonly Code  $code,
        private readonly array $adviceContainers,
    ) {}

    // region Build

    /**
     * Build the weaving class.
     *
     * @return string
     */
    public function build(): string
    {
        // Build the namespace
        $phpNamespace = $this->buildNamespace();

        // Build the class
        $class = $this->buildClass($phpNamespace);

        // Build the join points
        $class->addMember($this->buildJoinPoints());

        // Build the methods
        $methods = $this->buildMethods();
        foreach ($methods as $method) {
            $class->addMember($method);
        }

        // Add the class to the namespace
        $phpNamespace->add($class);

        $phpNamespace->addUse(DI::class);
        $phpNamespace->addUse(JoinPointInjector::class);

        // Build the file
        $file = (string)$phpNamespace;

        // Inject the JoinPoints
        $this->injectJoinPoints($file);

        return "<?php\n\n" . $file;
    }

    /**
     * Build the namespace.
     *
     * @return PhpNamespace
     */
    private function buildNamespace(): PhpNamespace
    {
        $reflectionClass = $this->code->getReflectionClass();
        return new PhpNamespace($reflectionClass->getNamespaceName());
    }

    /**
     * Build the class.
     *
     * @param PhpNamespace $phpNamespace
     *
     * @return ClassType
     */
    private function buildClass(PhpNamespace $phpNamespace): ClassType
    {
        $class = new ClassType();

        $reflectionClass = $this->code->getReflectionClass();

        // Set the class name
        $shortClassName = $reflectionClass->getShortName();
        $class->setName($shortClassName);

        // Add the use statement
        $className      = $reflectionClass->getName();
        $proxyClassName = $className . $this->cachePaths::PROXIED_SUFFIX;
        $phpNamespace->addUse($proxyClassName);

        // Set the class extends
        $class->setExtends($proxyClassName);

        // Set abstract
        $class->setAbstract($reflectionClass->isAbstract());

        return $class;
    }

    /**
     * Build the join points.
     *
     * @return Property
     */
    private function buildJoinPoints(): Property
    {
        // Build property
        $property = new Property(JoinPoint::JOIN_POINTS_PARAMETER_NAME);
        $property->setVisibility(ClassLike::VisibilityPrivate);
        $property->setStatic();
        $property->setType('array');

        // Build value
        $value = [];

        // Add interceptors
        foreach ($this->adviceContainers as $adviceContainer) {
            if ($adviceContainer instanceof MethodAdviceContainer) {
                $methodType = JoinPoint::TYPE_METHOD;

                foreach ($adviceContainer->getMatchedMethods() as $matchedMethod) {
                    $matchedRefMethod  = $matchedMethod->matchedRefMethod;
                    $matchedMethodName = $matchedRefMethod->getName();

                    $adviceContainerName = $adviceContainer->getName();

                    if (!in_array($adviceContainerName, $value[$methodType][$matchedMethodName] ?? [])) {
                        $value[$methodType][$matchedMethodName][] = $adviceContainerName;
                    }
                }
            }
        }

        $property->setValue($value);

        return $property;
    }

    /**
     * Build the methods.
     *
     * @return Method[]
     */
    private function buildMethods(): array
    {
        $methods = [];

        foreach ($this->adviceContainers as $adviceContainer) {
            if ($adviceContainer instanceof MethodAdviceContainer) {
                foreach ($adviceContainer->getMatchedMethods() as $matchedMethod) {
                    $refMethod  = $matchedMethod->matchedRefMethod;
                    $methodName = $refMethod->getName();

                    // Internal methods cannot be woven,
                    // so we skip them
                    if ($refMethod->getDeclaringClass()->isInternal()) {
                        continue;
                    }

                    // Check if the method was already built
                    if (array_key_exists($methodName, $methods)) {
                        continue;
                    }

                    // Build the method
                    $methods[$methodName] = $this->buildMethod($refMethod);
                }
            }
        }

        return $methods;
    }

    /**
     * Build the method.
     *
     * @param BetterReflectionMethod $refMethod
     *
     * @return Method
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    private function buildMethod(BetterReflectionMethod $refMethod): Method
    {
        $refMethod = new ReflectionMethod($refMethod);
        /** @noinspection PhpUnhandledExceptionInspection */
        $method = (new Factory)->fromMethodReflection(
            $refMethod,
        );

        $methodName = $refMethod->getName();

        foreach ($method->getParameters() as $parameter) {
            if ($parameter instanceof PromotedParameter) {
                $parameter->setReadOnly(false);
            }
        }

        // Add "return" if the method has a return type
        $return = (string)$method->getReturnType() !== 'void' ? 'return ' : '';

        // Add parameters as an array with the parameter name as key
        $parametersArray = $this->getParametersArray($refMethod);
        $parameters      = $parametersArray ? ", $parametersArray" : '';

        // Static methods don't have $this
        $isStatic = $refMethod->isStatic();
        $context  = $isStatic ? 'null' : '$this';

        $body = $return
            . 'call_user_func_array('
            . 'self::$' . JoinPoint::JOIN_POINTS_PARAMETER_NAME
            . '[\'' . JoinPoint::TYPE_METHOD . '\']'
            . '[\'' . $methodName . '\'], '
            . "[$context"
            . "$parameters]);";

        /**
         * @example
         * return call_user_func_array(self::$__joinPoints['method']['methodName'], [$this]);
         * return call_user_func_array(self::$__joinPoints['method']['methodName'], [null]);
         * return call_user_func_array(self::$__joinPoints['method']['methodName'], [$this, ['param1' => $param1, 'param2' => $param2]]);
         */

        $method->setBody($body);

        // Convert the method to public
        $method->setVisibility(ClassLike::VisibilityPublic);

        return $method;
    }

    /**
     * Create an associative array with the parameter name as key and the
     * parameter as value.
     *
     * @param ReflectionMethod $method
     *
     * @return string|null
     */
    private function getParametersArray(ReflectionMethod $method): ?string
    {
        $parameters = $method->getParameters();
        if (empty($parameters)) {
            return null;
        }

        $arguments = [];

        foreach ($parameters as $parameter) {
            $isRef = $parameter->isPassedByReference() ? '&' : '';
            $arguments[] = '\'' . $parameter->getName() . '\' => ' . $isRef . '$' . $parameter->getName();
        }

        return '[' . implode(', ', $arguments) . ']';
    }

    /**
     * Inject the JoinPoints.
     *
     * @param string $file
     *
     * @return void
     */
    private function injectJoinPoints(string &$file): void
    {
        $reflectionClass = $this->code->getReflectionClass();
        $shortClassName  = $reflectionClass->getShortName();

        // language=PHP
        $code = "DI::get(JoinPointInjector::class)->injectJoinPoints($shortClassName::class);";

        $file .= "\n" . $code;
    }

    // endregion
}
