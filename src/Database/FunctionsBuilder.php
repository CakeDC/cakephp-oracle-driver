<?php
declare(strict_types=1);

namespace CakeDC\OracleDriver\Database;

use Cake\Database\Expression\FunctionExpression;
use CakeDC\OracleDriver\Core\SingletonTrait;

/**
 * Contains methods related to generating FunctionExpression objects
 * with most commonly used Oracle SQL functions.
 * This acts as a factory for FunctionExpression objects.
 */
class FunctionsBuilder
{
    use SingletonTrait;

    /**
     * Default date format used when casting date columns to strings.
     *
     * @var string
     */
    protected string $_defaultDateFormat = 'YYYY-MM-DD HH24:MI:SS';

    /**
     * Returns a new instance of a FunctionExpression. This is used for generating
     * arbitrary function calls in the final SQL string.
     *
     * @param string $name the name of the SQL function to constructed
     * @param array $params list of params to be passed to the function
     * @param array $types list of types for each function param
     * @return \Cake\Database\Expression\FunctionExpression
     */
    protected function _build(string $name, array $params = [], array $types = []): FunctionExpression
    {
        return new FunctionExpression($name, $params, $types);
    }

    /**
     * Helper function to build a function expression argument that
     * only takes one literal argument.
     *
     * @param mixed $expression the function argument.
     * @return array
     */
    protected function _literalArgument(mixed $expression): array
    {
        if (is_string($expression)) {
            $expression = [$expression => 'literal'];
        } elseif (!is_array($expression)) {
            $expression = [$expression];
        }

        return $expression;
    }

    /**
     * Returns a FunctionExpression representing a call to TO_CHAR function.
     *
     * @param mixed $expression the function argument
     * @param array $types list of types to bind to the arguments
     * @return \Cake\Database\Expression\FunctionExpression
     */
    public static function toChar(mixed $expression, array $types = []): FunctionExpression
    {
        $builder = self::getInstance();
        $args = [];
        $args += $builder->_literalArgument($expression);

        return $builder->_build('TO_CHAR', $args, $types);
    }

    /**
     * Returns a FunctionExpression representing a call to SQL TO_CHAR function.
     *
     * @param mixed $expression the function argument
     * @param mixed $format the function argument
     * @param array $types list of types to bind to the arguments
     * @return \Cake\Database\Expression\FunctionExpression
     */
    public static function toCharWithFormat(mixed $expression, mixed $format = null, array $types = []): FunctionExpression
    {
        $builder = self::getInstance();
        $args = [];
        $format ??= $builder->_defaultDateFormat;

        $args += $builder->_literalArgument($expression);
        $args[] = $format;

        return $builder->_build('TO_CHAR', $args, $types);
    }

    /**
     * Returns a FunctionExpression representing a call to SQL TO_DATE function.
     *
     * @param mixed $expression the function argument
     * @param mixed $format the function argument
     * @param array $types list of types to bind to the arguments
     * @return \Cake\Database\Expression\FunctionExpression
     */
    public static function toDate(mixed $expression, mixed $format = null, array $types = []): FunctionExpression
    {
        $builder = self::getInstance();
        $args = [];
        $format ??= $builder->_defaultDateFormat;

        $args += $builder->_literalArgument($expression);
        $args[] = $format;

        return $builder->_build('TO_DATE', $args, $types);
    }

    /**
     * Magic method dispatcher to create custom SQL function calls
     *
     * @param string $name the SQL function name to construct
     * @param array $args list with up to 2 arguments, first one being an array with
     * parameters for the SQL function and second one a list of types to bind to those
     * params
     * @return \Cake\Database\Expression\FunctionExpression
     */
    public function __call(string $name, array $args): FunctionExpression
    {
        $builder = self::getInstance();

        return match (count($args)) {
            0 => $builder->_build($name),
            1 => $builder->_build($name, $args[0]),
            default => $builder->_build($name, $args[0], $args[1]),
        };
    }
}
