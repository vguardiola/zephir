<?php

/*
 +--------------------------------------------------------------------------+
 | Zephir Language                                                          |
 +--------------------------------------------------------------------------+
 | Copyright (c) 2013-2014 Zephir Team and contributors                     |
 +--------------------------------------------------------------------------+
 | This source file is subject the MIT license, that is bundled with        |
 | this package in the file LICENSE, and is available through the           |
 | world-wide-web at the following url:                                     |
 | http://zephir-lang.com/license.html                                      |
 |                                                                          |
 | If you did not receive a copy of the MIT license and are unable          |
 | to obtain it through the world-wide-web, please send a note to           |
 | license@zephir-lang.com so we can mail you a copy immediately.           |
 +--------------------------------------------------------------------------+
*/

namespace Zephir\Operators\Comparison;

use Zephir\Operators\BaseOperator;
use Zephir\CompilationContext;
use Zephir\Expression;
use Zephir\CompiledExpression;
use Zephir\CompilerException;

/**
 * BaseOperator
 *
 * This is the base operator for comparison operators
 */
class ComparisonBaseOperator extends BaseOperator
{
    protected $_literalOnly = true;

    protected $_commutative = false;

    /**
     * @param $expr
     * @param CompilationContext $compilationContext
     * @return bool|CompiledExpression
     * @throws CompilerException
     */
    public function optimizeTypeOf($expr, CompilationContext $compilationContext)
    {
        if (!isset($expr['left'])) {
            return false;
        }

        if (!isset($expr['right']) && !isset($expr['right']['value'])) {
            return false;
        }

        if ($expr['left']['type'] == 'typeof') {
            if ($expr['right']['type'] != 'string') {
                echo $expr['right']['type'];
                $compilationContext->logger->warning("Possible invalid comparison for 'typeof' operator with non-string", "invalid-typeof-comparison", $expr['right']);
                return false;
            }

            if (isset($expr['type'])) {
                switch ($expr['type']) {
                    case 'identical':
                    case 'equals':
                        $operator = '==';
                        break;

                    case 'not-identical':
                    case 'not-equals':
                        $operator = '!=';
                        break;

                    default:
                        return false;
                }
            }

            $expression = new Expression($expr['left']['left']);
            $condition = $expression->compile($compilationContext);
            $variableVariable = $compilationContext->symbolTable->getVariableForRead($condition->getCode(), $compilationContext, $expr);

            if ($expr['right']['type'] != 'string') {
                throw new CompilerException('Right expression of typeof operator must be "string" type', $expr['right']);
            }

            $value = strtolower($expr['right']['value']);

            switch ($variableVariable->getType()) {
                case 'variable':
                    switch ($value) {

                        case 'array':
                            $condition = '(Z_TYPE_P(' . $variableVariable->getName() . ') ' . $operator . ' IS_ARRAY)';
                            break;

                        case 'object':
                            $condition = '(Z_TYPE_P(' . $variableVariable->getName() . ') ' . $operator . ' IS_OBJECT)';
                            break;

                        case 'null':
                            $condition = '(Z_TYPE_P(' . $variableVariable->getName() . ') ' . $operator . ' IS_NULL)';
                            break;

                        case 'string':
                            $condition = '(Z_TYPE_P(' . $variableVariable->getName() . ') ' . $operator . ' IS_STRING)';
                            break;

                        case 'int':
                        case 'integer':
                        case 'long':
                            $condition = '(Z_TYPE_P(' . $variableVariable->getName() . ') ' . $operator . ' IS_LONG)';
                            break;

                        case 'boolean':
                        case 'bool':
                            $condition = '(Z_TYPE_P(' . $variableVariable->getName() . ') ' . $operator . ' IS_BOOL)';
                            break;

                        case 'resource':
                            $condition = '(Z_TYPE_P(' . $variableVariable->getName() . ') ' . $operator . ' IS_RESOURCE)';
                            break;

                        default:
                            throw new CompilerException('Unknown type: "' . $value . '" in typeof comparison', $expr['right']);
                    }
                    break;

                default:
                    return false;
            }

            return new CompiledExpression('bool', $condition, $expr);
        }

        return false;
    }

    /**
     * Compile the expression
     *
     * @param array $expression
     * @param CompilationContext $compilationContext
     */
    public function compile($expression, CompilationContext $compilationContext)
    {
        $conditions = $this->optimizeTypeOf($expression, $compilationContext);
        if ($conditions !== false) {
            return $conditions;
        }

        if (!isset($expression['left'])) {
            throw new CompilerException("Missing left part of the expression", $expression);
        }

        if (!isset($expression['right'])) {
            throw new CompilerException("Missing right part of the expression", $expression);
        }

        $leftExpr = new Expression($expression['left']);
        $leftExpr->setReadOnly(true);
        $left = $leftExpr->compile($compilationContext);

        $rightExpr = new Expression($expression['right']);
        $rightExpr->setReadOnly(true);
        $right = $rightExpr->compile($compilationContext);

        switch ($left->getType()) {

            case 'null':
                switch ($right->getType()) {

                    case 'null':
                        return new CompiledExpression('bool', '(0 ' . $this->_operator . ' 0)', $expression);

                    case 'int':
                    case 'uint':
                    case 'long':
                    case 'ulong':
                        return new CompiledExpression('bool', '(0 ' . $this->_operator . ' ' . $right->getCode() . ')', $expression);

                    case 'char':
                    case 'uchar':
                        return new CompiledExpression('bool', '(\'\\0\' ' . $this->_operator . ' \'' . $right->getCode() . '\')', $expression);

                    case 'double':
                        return new CompiledExpression('bool', '(0 ' . $this->_operator . ' (int) ' . $right->getCode() . ')', $expression);

                    case 'variable':
                        $variableRight = $compilationContext->symbolTable->getVariableForRead($right->getCode(), $compilationContext, $expression['left']);
                        switch ($variableRight->getType()) {

                            case 'int':
                            case 'uint':
                            case 'long':
                            case 'ulong':
                                $compilationContext->headersManager->add('kernel/operators');
                                return new CompiledExpression('bool', '(0 ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                            case 'double':
                                $compilationContext->headersManager->add('kernel/operators');
                                return new CompiledExpression('bool', '(0 ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                            case 'variable':
                                $compilationContext->headersManager->add('kernel/operators');
                                return new CompiledExpression('bool', '(Z_TYPE_P(' . $variableRight->getName() . ') == IS_NULL)', $expression);

                            default:
                                throw new CompilerException("Unknown type: " . $variableRight->getType(), $expression['right']);
                        }
                        break;

                    default:
                        throw new CompilerException("Unknown type: " . $right->getType(), $expression);
                }
                break;

            case 'int':
            case 'uint':
            case 'long':
            case 'ulong':
            case 'int':
            case 'char':
            case 'uchar':
                switch ($right->getType()) {

                    case 'null':
                        return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' 0)', $expression);

                    case 'int':
                    case 'uint':
                    case 'long':
                    case 'ulong':
                        return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' ' . $right->getCode() . ')', $expression);

                    case 'char':
                    case 'uchar':
                        return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' \'' . $right->getCode() . '\')', $expression);

                    case 'double':
                        return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' (int) ' . $right->getCode() . ')', $expression);

                    case 'variable':
                        $variableRight = $compilationContext->symbolTable->getVariableForRead($right->getCode(), $compilationContext, $expression['left']);
                        switch ($variableRight->getType()) {

                            case 'int':
                            case 'uint':
                            case 'long':
                            case 'ulong':
                                $compilationContext->headersManager->add('kernel/operators');
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                            case 'double':
                                $compilationContext->headersManager->add('kernel/operators');
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                            case 'variable':
                                $compilationContext->headersManager->add('kernel/operators');
                                return new CompiledExpression('bool', $this->_zvalLongNegOperator . '(' . $variableRight->getName() . ', ' . $left->getCode() . ')', $expression);

                            default:
                                throw new CompilerException("Unknown type: " . $variableRight->getType(), $expression['right']);
                        }
                        break;

                    default:
                        throw new CompilerException("Cannot compare " . $left->getType() . " with " . $right->getType(), $expression);
                }
                break;

            case 'bool':

                switch ($right->getType()) {

                    case 'null':
                        return new CompiledExpression('bool', '(' . $left->getBooleanCode() . ' ' . $this->_operator . ' 0)', $expression);

                    case 'int':
                    case 'uint':
                    case 'long':
                    case 'ulong':
                        return new CompiledExpression('bool', '(' . $left->getBooleanCode() . ' ' . $this->_operator . ' ' . $right->getCode() . ')', $expression);

                    case 'char':
                    case 'uchar':
                        return new CompiledExpression('bool', '(' . $left->getBooleanCode() . ' ' . $this->_operator . ' \'' . $right->getCode() . '\')', $expression);

                    case 'double':
                        return new CompiledExpression('bool', '(' . $left->getBooleanCode() . ' ' . $this->_operator . ' (int) ' . $right->getCode() . ')', $expression);

                    case 'bool':
                        return new CompiledExpression('bool', '(' . $left->getBooleanCode() . ' ' . $this->_operator . ' ' . $right->getBooleanCode() . ')', $expression);

                    case 'variable':
                        $variableRight = $compilationContext->symbolTable->getVariableForRead($right->getCode(), $compilationContext, $expression['left']);
                        switch ($variableRight->getType()) {

                            case 'int':
                            case 'uint':
                            case 'long':
                            case 'ulong':
                                $compilationContext->headersManager->add('kernel/operators');
                                return new CompiledExpression('bool', '(' . $left->getBooleanCode() . ' ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                            case 'double':
                                $compilationContext->headersManager->add('kernel/operators');
                                return new CompiledExpression('bool', '(' . $left->getBooleanCode() . ' ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                            case 'variable':
                                $compilationContext->headersManager->add('kernel/operators');
                                return new CompiledExpression('bool', 'ZEPHIR_IS_BOOL(' . $variableRight->getName() . ', ' . $left->getCode() . ')', $expression);

                            default:
                                throw new CompilerException("Unknown type: " . $variableRight->getType(), $expression['right']);
                        }
                        break;

                    default:
                        throw new CompilerException("Cannot compare " . $left->getType() . " with " . $right->getType(), $expression);
                }
                break;

            case 'variable':

                $variable = $compilationContext->symbolTable->getVariableForRead($left->getCode(), $compilationContext, $expression['left']);
                switch ($variable->getType()) {

                    case 'int':
                    case 'uint':
                    case 'long':
                    case 'ulong':
                    case 'char':
                    case 'uchar':

                        switch ($right->getType()) {

                            case 'int':
                            case 'uint':
                            case 'long':
                            case 'ulong':
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' ' . $right->getCode() . ')', $expression);

                            case 'char':
                            case 'uchar':
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' \'' . $right->getCode() . '\')', $expression);

                            case 'bool':
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' ' . $right->getBooleanCode() . ')', $expression);

                            case 'variable':

                                $variableRight = $compilationContext->symbolTable->getVariableForRead($right->getCode(), $compilationContext, $expression['left']);
                                switch ($variableRight->getType()) {

                                    case 'int':
                                    case 'uint':
                                    case 'long':
                                    case 'ulong':
                                    case 'char':
                                    case 'uchar':
                                    case 'double':
                                        return new CompiledExpression('bool', '(' . $variable->getName() . ' ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                                    case 'variable':
                                        $compilationContext->headersManager->add('kernel/operators');
                                        return new CompiledExpression('bool', $this->_zvalLongNegOperator . '(' . $variableRight->getName() . ', ' . $variable->getName() . ')', $expression);

                                    default:
                                        throw new CompilerException("Unknown type: " . $variableRight->getType(), $expression['right']);
                                }
                                break;

                            default:
                                throw new CompilerException("Cannot compare variable: " . $variable->getType() . " with: " . $right->getType(), $expression);
                        }
                        break;

                    case 'double':
                        switch ($right->getType()) {

                            case 'int':
                            case 'uint':
                            case 'long':
                            case 'ulong':
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' ' . $right->getCode() . ')', $expression);

                            case 'bool':
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' ' . $right->getBooleanCode() . ')', $expression);

                            case 'char':
                            case 'uchar':
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' \'' . $right->getCode() . '\')', $expression);

                            case 'variable':

                                $variableRight = $compilationContext->symbolTable->getVariableForRead($right->getCode(), $compilationContext, $expression['left']);
                                switch ($variableRight->getType()) {
                                    case 'int':
                                    case 'uint':
                                    case 'long':
                                    case 'ulong':
                                        return new CompiledExpression('bool', '(' . $variable->getName() . ' ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                                    case 'double':
                                        return new CompiledExpression('bool', '(' . $variable->getName() . ' ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                                    case 'variable':
                                        $compilationContext->headersManager->add('kernel/operators');
                                        return new CompiledExpression('bool', 'ZEPHIR_IS_DOUBLE(' . $variableRight->getName() . ', ' . $variable->getName() . ')', $expression);

                                    default:
                                        throw new CompilerException("Unknown type: " . $variableRight->getType(), $expression['right']);
                                }
                                break;

                            default:
                                throw new CompilerException("Cannot compare variable: " . $variable->getType() . " with: " . $right->getType(), $expression);
                        }
                        break;

                    case 'bool':
                        switch ($right->getType()) {
                            case 'int':
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' ' . $right->getCode() . ')', $expression['left']);

                            case 'bool':
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' ' . $right->getBooleanCode() . ')', $expression['left']);

                            case 'null':
                                return new CompiledExpression('bool', '(' . $left->getCode() . ' ' . $this->_operator . ' 0)', $expression['left']);

                            case 'variable':
                                $variableRight = $compilationContext->symbolTable->getVariableForRead($right->getCode(), $compilationContext, $expression['left']);
                                switch ($variableRight->getType()) {

                                    case 'int':
                                    case 'uint':
                                    case 'long':
                                    case 'ulong':
                                        return new CompiledExpression('bool', '(' . $variable->getName() . ' ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                                    case 'bool':
                                        return new CompiledExpression('bool', '(' . $variable->getName() . ' ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                                    case 'double':
                                        return new CompiledExpression('bool', '(' . $variable->getName() . ' ' . $this->_operator . ' ' . $variableRight->getName() . ')', $expression);

                                    case 'variable':
                                        $compilationContext->headersManager->add('kernel/operators');
                                        return new CompiledExpression('bool', $this->_zvalBoolOperator . '(' . $variableRight->getName() . ', ' . $variable->getName() . ')', $expression);

                                    default:
                                        throw new CompilerException("Unknown type: " . $variableRight->getType(), $expression['right']);
                                }
                                break;

                            default:
                                throw new CompilerException("Cannot compare variable: " . $variable->getType() . " with: " . $right->getType(), $expression);
                        }
                        break;

                    case 'string':
                        switch ($right->getType()) {

                            case 'null':
                                $compilationContext->headersManager->add('kernel/operators');
                                if ($variable->isLocalOnly()) {
                                    return new CompiledExpression('bool', $this->_zvalStringOperator . '(&' . $variable->getName() . ', "")', $expression['left']);
                                } else {
                                    return new CompiledExpression('bool', $this->_zvalStringOperator . '(' . $variable->getName() . ', "")', $expression['left']);
                                }
                                break;

                            case 'string':
                                $compilationContext->headersManager->add('kernel/operators');
                                if ($variable->isLocalOnly()) {
                                    return new CompiledExpression('bool', $this->_zvalStringOperator . '(&' . $variable->getName() . ', "' . $right->getCode() . '")', $expression['left']);
                                } else {
                                    return new CompiledExpression('bool', $this->_zvalStringOperator . '(' . $variable->getName() . ', "' . $right->getCode() . '")', $expression['left']);
                                }
                                break;

                            case 'variable':
                                $variableRight = $compilationContext->symbolTable->getVariableForRead($right->getCode(), $compilationContext, $expression['left']);
                                switch ($variableRight->getType()) {

                                    case 'string':
                                    case 'variable':
                                        $compilationContext->headersManager->add('kernel/operators');
                                        if ($variable->isLocalOnly()) {
                                            return new CompiledExpression('bool', $this->_zvalOperator . '(&' . $variable->getName() . ', ' . $variableRight->getName() . ')', $expression);
                                        } else {
                                            return new CompiledExpression('bool', $this->_zvalOperator . '(' . $variable->getName() . ', ' . $variableRight->getName() . ')', $expression);
                                        }
                                        break;

                                    default:
                                        throw new CompilerException("Unknown type: " . $variableRight->getType(), $expression['right']);
                                }
                                break;

                            default:
                                throw new CompilerException("Unknown type: " . $right->getType(), $expression['left']);
                        }
                        break;

                    case 'variable':
                        switch ($right->getType()) {

                            case 'null':
                                $compilationContext->headersManager->add('kernel/operators');
                                if ($variable->isLocalOnly()) {
                                    return new CompiledExpression('bool', '(Z_TYPE_P(&' . $variable->getName() . ') ' . $this->_operator . ' IS_NULL)', $expression['left']);
                                } else {
                                    return new CompiledExpression('bool', '(Z_TYPE_P(' . $variable->getName() . ') ' . $this->_operator . ' IS_NULL)', $expression['left']);
                                }
                                break;

                            case 'int':
                            case 'uint':
                            case 'long':
                            case 'ulong':
                                $compilationContext->headersManager->add('kernel/operators');
                                if ($variable->isLocalOnly()) {
                                    return new CompiledExpression('bool', $this->_zvalLongOperator . '(&' . $variable->getName() . ', ' . $right->getCode() . ')', $expression['left']);
                                } else {
                                    return new CompiledExpression('bool', $this->_zvalLongOperator . '(' . $variable->getName() . ', ' . $right->getCode() . ')', $expression['left']);
                                }
                                break;

                            case 'char':
                            case 'uchar':
                                $compilationContext->headersManager->add('kernel/operators');
                                if ($variable->isLocalOnly()) {
                                    return new CompiledExpression('bool', $this->_zvalLongOperator . '(&' . $variable->getName() . ', \'' . $right->getCode() . '\')', $expression['left']);
                                } else {
                                    return new CompiledExpression('bool', $this->_zvalLongOperator . '(' . $variable->getName() . ', \'' . $right->getCode() . '\')', $expression['left']);
                                }
                                break;

                            case 'bool':
                                $compilationContext->headersManager->add('kernel/operators');
                                if ($right->getCode() == 'true') {
                                    if ($variable->isLocalOnly()) {
                                        return new CompiledExpression('bool', $this->_zvalBoolTrueOperator . '(&' . $variable->getName() . ')', $expression['left']);
                                    } else {
                                        return new CompiledExpression('bool', $this->_zvalBoolTrueOperator . '(' . $variable->getName() . ')', $expression['left']);
                                    }
                                } else {
                                    if ($variable->isLocalOnly()) {
                                        return new CompiledExpression('bool', $this->_zvalBoolFalseOperator . '(&' . $left->getCode() . ')', $expression['left']);
                                    } else {
                                        return new CompiledExpression('bool', $this->_zvalBoolFalseOperator . '(' . $left->getCode() . ')', $expression['left']);
                                    }
                                }
                                break;

                            case 'string':
                                $compilationContext->headersManager->add('kernel/operators');
                                if ($variable->isLocalOnly()) {
                                    return new CompiledExpression('bool', $this->_zvalStringOperator . '(&' . $variable->getName() . ', "' . $right->getCode() . '")', $expression['left']);
                                } else {
                                    return new CompiledExpression('bool', $this->_zvalStringOperator . '(' . $variable->getName() . ', "' . $right->getCode() . '")', $expression['left']);
                                }
                                break;

                            case 'variable':
                                $variableRight = $compilationContext->symbolTable->getVariableForRead($right->getCode(), $compilationContext, $expression['left']);
                                switch ($variableRight->getType()) {
                                    case 'int':
                                    case 'uint':
                                    case 'long':
                                    case 'ulong':
                                        $compilationContext->headersManager->add('kernel/operators');
                                        if ($variable->isLocalOnly()) {
                                            return new CompiledExpression('bool', $this->_zvalLongOperator . '(&' . $variable->getName() . ', ' . $variableRight->getName() . ')', $expression);
                                        } else {
                                            return new CompiledExpression('bool', $this->_zvalLongOperator . '(' . $variable->getName() . ', ' . $variableRight->getName() . ')', $expression);
                                        }
                                        break;

                                    case 'double':
                                        $compilationContext->headersManager->add('kernel/operators');
                                        if ($variable->isLocalOnly()) {
                                            return new CompiledExpression('bool', $this->_zvalDoubleOperator . '(&' . $variable->getName() . ', ' . $variableRight->getName() . ')', $expression);
                                        } else {
                                            return new CompiledExpression('bool', $this->_zvalDoubleOperator . '(' . $variable->getName() . ', ' . $variableRight->getName() . ')', $expression);
                                        }
                                        break;

                                    case 'string':
                                    case 'variable':
                                        $compilationContext->headersManager->add('kernel/operators');
                                        if ($variable->isLocalOnly()) {
                                            return new CompiledExpression('bool', $this->_zvalOperator . '(&' . $variable->getName() . ', ' . $variableRight->getName() . ')', $expression);
                                        } else {
                                            return new CompiledExpression('bool', $this->_zvalOperator . '(' . $variable->getName() . ', ' . $variableRight->getName() . ')', $expression);
                                        }
                                        break;

                                    default:
                                        throw new CompilerException("Unknown type: " . $variableRight->getType(), $expression['right']);
                                }
                                break;

                            default:
                                throw new CompilerException("Unknown type: " . $right->getType(), $expression['left']);
                        }
                        break;

                    default:
                        throw new CompilerException("Unknown type: " . $variable->getType(), $expression);
                }
                break;

            default:
                throw new CompilerException("Unknown type: " . $left->getType(), $expression);
        }

    }
}
