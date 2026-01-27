<?php

declare(strict_types=1);

namespace Cyrtolat\SpiralRuntimeDiagnostics\Support;

/**
 * CallableNameFormatter: best-effort конвертация callable в читаемую строку.
 */
final class CallableNameFormatter
{
    /**
     * Конвертирует произвольный callable в читаемую строку (best-effort).
     *
     * Поддерживаемые варианты:
     * - [object, 'method'] -> Class::method
     * - [ClassName::class, 'method'] -> ClassName::method
     * - invokable object -> Class::__invoke
     * - string function name -> как есть
     * - \Closure -> {closure} или ScopeClass::{closure}
     *
     * @param mixed $callable Любое значение, из которого можно best-effort извлечь имя вызова.
     *
     * @throws \ReflectionException практически не кидаемое исключение
     * @return string Человекочитаемое имя callable. Может быть пустой строкой, если имя построить нельзя.
     */
    public static function parseCallable(mixed $callable): string
    {
        if ($callable === null) {
            return '';
        }

        // [obj, 'method'] or [ClassName::class, 'method']
        if (is_array($callable) and (count($callable) === 2)) {
            [$objOrClass, $method] = $callable;

            if (is_object($objOrClass) and is_string($method) and ($method !== '')) {
                return $objOrClass::class . '::' . $method;
            }

            if (is_string($objOrClass) and ($objOrClass !== '') and is_string($method) and ($method !== '')) {
                return $objOrClass . '::' . $method;
            }
        }

        // когда имя функции
        if (is_string($callable)) {
            return $callable;
        }

        // closure: best-effort
        if ($callable instanceof \Closure) {
            $rf = new \ReflectionFunction($callable);
            $scope = $rf->getClosureScopeClass();

            if ($scope !== null) {
                return $scope->getName() . '::{closure}';
            }

            return '{closure}';
        }

        // invokable object
        if (is_object($callable) and method_exists($callable, '__invoke')) {
            return $callable::class . '::__invoke';
        }

        return '';
    }
}
