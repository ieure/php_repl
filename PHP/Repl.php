<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * A REPL for PHP
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Repl
 * @author     Ian Eure <ieure@php.net>
 * @copyright  2009 Ian Eure.
 * @filesource
 */

/**
 * PHP_Repl
 *
 * @package    PHP_Repl
 * @author     Ian Eure <ieure@php.net>
 * @version    @package_version@
 */
class PHP_Repl
{
    /**
     * Where we're reading input from
     *
     * @var resource
     */
    private $input;

    /**
     * The options for this instance
     *
     * @var array
     */
    private $options = array();

    /**
     * The path to the configuration file
     *
     * @var string
     */
    private $rc_file;


    /**
     * Constructor
     *
     * @return void
     */
    public function __construct($options = array())
    {
        $this->input   = fopen('php://stdin', 'r');
        $this->rc_file = getenv('PHPREPLRC') ? getenv('PHPREPLRC') :
            getenv('HOME') . '/.phpreplrc';

        $defaults      = $this->defaultOptions();
        $this->options = array_merge($defaults, $options);

        if ($this->options['readline'] &&
            is_readable($this->options['readline_hist'])) {
            readline_read_history($this->options['readline_hist']);
        }
    }

    /**
     * Get default options
     *
     * @return array Defaults
     */
    private function defaultOptions()
    {
        $defaults = array('prompt'        => 'php> ',
                          'readline'      => true,
                          'readline_hist' => getenv('HOME') .
                          '/.phprepl_history');

        if (!function_exists('readline') || getenv('TERM') == 'dumb') {
            $defaults['readline'] = false;
        }

        if (is_readable($this->rc_file)) {
            $defaults = array_merge($defaults, parse_ini_file($this->rc_file));
        }
        return $defaults;
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        fclose($this->input);
        if ($this->options['readline']) {
            readline_write_history($this->options['readline_hist']);
        }

        // Save config
        $fp = fopen($this->rc_file, 'w');
        if ($fp === false) {
            return;
        }
        foreach ($this->options as $k => $v) {
            fwrite($fp, "$k = \"$v\"\n");
        }
        fclose($fp);
    }

    /**
     * Run the main loop
     *
     * @param array $scope Scope to pass into the REPL.
     *
     * @return void
     */
    public function run(array $scope = array())
    {
        extract($scope);
        ob_start();
        while (true) {
            // inner loop is to escape from stacked output buffers
            while ($__ob__ = ob_get_clean() ) {
                echo $__ob__;
                unset($__ob__);
            }

            try {
                if (((boolean) $__code__ = $this->read()) === false) {
                    break;
                }
                ob_start(array($this, 'ob_cleanup'));
                ob_implicit_flush(true);
                error_reporting(E_ALL | E_STRICT);
                ini_set('html_errors', 'Off');
                ini_set('display_errors', 'On');

                $this->_print($_ = eval($this->cleanup($__code__)));
            } catch (Exception $e) {
                echo ($_ = $e) . "\n";
            }
        }
    }

    /**
     * Read input
     *
     * @param
     *
     * @return string Input
     */
    private function read()
    {
        $code  = '';
        $done  = false;
        $lines = 0;
        static $shifted;
        if (!$shifted) {
            // throw away argv[0]
            array_shift($_SERVER['argv']);
            $shifted = true;
        }
        do {
            $prompt = $lines > 0 ? '> ' : $this->options['prompt'];
            if (count($_SERVER['argv'])) {
                $line = array_shift($_SERVER['argv']);
            } elseif ($this->options['readline']) {
                $line = readline($prompt);
            } else {
                echo $prompt;
                $line = fgets($this->input);
            }

            // If the input was empty, return false; this breaks the loop.
            if ($line === false) {
                return false;
            }

            $line = trim($line);
            // If the last char is a backslash, remove it and
            // accumulate more lines.
            if (substr($line, -1) == '\\') {
                $line = substr($line, 0, strlen($line) - 1);
            } else {
                $done = true;
            }
            $code .= $line;
            $lines++;
        } while (!$done);

        // Add the whole block to the readline history.
        if ($this->options['readline']) {
            readline_add_history($code);
        }
        return $code;
    }

    /**
     * Clean up the read string
     *
     * @param string $input The input we read
     *
     * @return string Cleaned up code to eval
     */
    private function cleanup($input)
    {
        static $implicit = array(T_RETURN, T_THROW, T_CLASS, T_FUNCTION,
                                 T_INTERFACE, T_ABSTRACT, T_STATIC, T_ECHO,
                                 T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE,
                                 T_REQUIRE_ONCE, T_TRY, ';');
        static $sugar    = array(',' => 'dissect',
                                 'd' => 'doc',
                                 'l' => 'dir',
                                 'e' => 'cleanup');
        static $strip = array(T_COMMENT, T_DOC_COMMENT);
        static $last;

        $input = trim($input);
        $tokens = token_get_all("<?php {$input}");

        // Sugar
        if (substr($input, 0, 1) == ',' &&
            isset($sugar[$m = substr($input, 1, 1)])) {
            $input = preg_replace('/^,.\s*/', '', $input);
            if (empty($input)) {
                $input = $last;
            }

            // if the input string contains anything but a single variable,
            // wrap it in single-quotes
            if (!(count($tokens) == 2 && isset($tokens[1][0]) &&
                    $tokens[1][0] = T_VARIABLE)) {
                $input = "'". str_replace("'", "\\'", $input) . "'";
            }
            return $this->cleanup("\$this->{$sugar[$m]}($input)");
        }

        // drop comments, add trailing semicolon (recreate input)
        $semicount = 0;
        $input = '';
        // skip the first token (T_OPEN_TAG)
        for ($i = 1, $ii = count($tokens); $i < $ii; $i++) {
            if (is_array($tokens[$i])) {
                if (!in_array($tokens[$i][0], $strip)) {
                    $input .= $tokens[$i][1]; // token value
                }
            } else {
                if (';' === $tokens[$i]) {
                    ++$semicount;
                }
                $input .= $tokens[$i]; // token _is_ value
            }
        }
        if (';' !== $tokens[$i-1]) {
            // Add a trailing semicolon if the last token is not one already
            ++$semicount;
            $input .= ';';
        }
        // grab the "first" token's value
        if (isset($tokens[1])) {
            if (is_array($tokens[1])) {
                $first = $tokens[1][0];
            } else {
                $first = $tokens[1];
            }
        } else {
            $first = null;
        }

        // Make sure we get a value back from eval()
        if (!in_array($first, $implicit) && (1 === $semicount)) {
            $input = 'return ' . $input;
        }

        return $last = $input;
    }

    /**
     * Clean up output captured from eval()'d code
     *
     * @param string $output Output from the code
     *
     * @return string Cleaned up output
     */
    public function ob_cleanup($output)
    {
        if (strlen($output) > 0 && substr($output, -1) != "\n") {
            $output .= "\n";
        }
        return $output;
    }

    /**
     * Print the output of some code
     *
     * @param mixed $out The output
     *
     * @return void
     */
    private function _print($out)
    {
        $type = gettype($out);
        switch ($type) {
        case 'NULL':
            break;

        case 'double':
        case 'float':
        case 'integer':
        case 'boolean':
            var_dump($out);
            break;

        case 'string':
        case 'array':
            var_export($out);
            echo "\n";
            break;

        default:
            print_r($out);
        }
    }

    /**
     * Get reflection for something
     *
     * @param string $thing The thing to get reflection for
     *
     * @return mixed ReflectionFoo instance
     */
    protected function getReflection($thing)
    {
        switch (true) {
        case is_object($thing):
            return new ReflectionObject($thing);

        case class_exists($thing, false):
            return new ReflectionClass($thing);

        case function_exists($thing):
            return new ReflectionFunction($thing);

        case strstr($thing, '::'):
            list($class, $what) = explode('::', $thing);
            $rc = new ReflectionClass($class);

            switch (true) {
            case substr($what, -2) == '()':
                $what = substr($what, 0, strlen($what) - 2);
            case $rc->hasMethod($what):
                return $rc->getMethod($what);

            case substr($what, 0, 1) == '$':
                $what = substr($what, 1);
            case $rc->hasProperty($what):
                return $rc->getProperty($what);

            case $rc->hasConstant($what):
                return $rc->getConstant($what);
            }

        case is_string($thing):
            return var_export($thing) . "\n";
        }
    }

    /**
     * Dissect something
     *
     * @param mixed $thing The thing to dissect
     *
     * @return void
     */
    protected function dissect($thing)
    {
        echo (string) $ref = $this->getReflection($thing);
        return "---";
    }

    /**
     * Get a list of methods and properties of a class
     *
     * @param mixed $thing The thing to dissect
     *
     * @return void
     */
    protected function dir($thing)
    {
        $rc = $this->getReflection($thing);
        foreach ($rc->getProperties() as $prop) {
            echo "\${$prop->getName()}\n";
        }
        foreach ($rc->getMethods() as $meth) {
            echo "\{$meth->getName()}()\n";
        }
        return "---";
    }

    /**
     * Get documentation for something
     *
     * @param mixed $thing The thing to dissect
     *
     * @return void
     */
    protected function doc($thing)
    {
        if ($r = $this->getReflection($thing)) {
            echo preg_replace('/^\s*\*/m', ' *',
                          $r->getDocComment()) . "\n";
        } else {
            echo "(no doc)\n";
        }
        return "---";
    }
}

?>
