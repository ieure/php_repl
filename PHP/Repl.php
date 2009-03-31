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
        $this->rc_file = isset($_ENV['PHPREPLRC']) ? $_ENV['PHPREPLRC'] :
            $_ENV['HOME'] . '/.phpreplrc';

        $defaults      = $this->defaultOptions();
        $this->options = array_merge($defaults, $options);
        if ($this->options['autorun']) {
            $this->run();
        }

        if ($this->options['readline'] &&
            is_readable($this->options['readline_hist'])) {
            array_map('readline_add_history',
                      file($this->options['readline_hist']));
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
                          'autorun'       => false,
                          'readline'      => true,
                          'readline_hist' => $_ENV['HOME'] .
                          '/.phprepl_history');

        if (!function_exists('readline') || $_ENV['TERM'] == 'dumb') {
            $defaults['readline'] = false;
        }

        if (is_readable($this->rc_file)) {
            $rc_defaults = parse_ini_file($this->rc_file);
            if (isset($rc_defaults['autorun'])) {
                unset($rc_defaults['autorun']);
            }
            $defaults = array_merge($defaults, $rc_defaults);
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
     * @return void
     */
    public function run()
    {
        while (($__code__ = $this->read()) != false) {
            try {
                ob_start(array($this, 'ob_cleanup'));
                ob_implicit_flush(true);
                error_reporting(E_ALL | E_STRICT);
                ini_set('html_errors', 'Off');
                ini_set('display_errors', 'On');
                $_ = eval($__code__);
                ob_flush();
                ob_end_clean();
                $this->_print($_);
            } catch (Exception $e) {
                $_ = $e;
                ob_flush();
                ob_end_clean();
                echo $e . "\n";
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
        if ($this->options['readline']) {
            $line = readline($this->options['prompt']);
            readline_add_history($line);
        } else {
            echo $this->options['prompt'];
            $line = fgets($this->input);
        }

        return $line === false ? false : $this->cleanup($line);
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
        static $implicit = array('return', 'throw', 'class', 'function',
                                 'interface', 'abstract', 'static', 'echo',
                                 'include', 'include_once', 'require',
                                 'require_once');
        static $sugar = array(',' => 'dissect',
                              'd' => 'doc',
                              'l' => 'dir');

        $input = trim($input);

        if (substr($input, 0, 1) == ',' &&
            isset($sugar[$m = substr($input, 1, 1)])) {
                call_user_func_array(array($this, $sugar[$m]),
                                     trim(substr($input, 2)));
            return 'return null;';
        }

        // Add a trailing semicolon
        if (substr($input, -1) != ';') {
            $input .= ';';
        }

        // Make sure we get a value back from eval()
        $first = substr($input, 0, strpos($input, " "));
        if (!in_array($first, $implicit)) {
            $input = 'return ' . $input;
        }
        return $input;
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
        case class_exists($thing, false):
            return new ReflectionClass($thing);

        case function_exists($thing):
            return new ReflectionFunction($thing);
        }

        if (strstr($thing, '::')) {
            list($class, $method) = explode('::', $thing);
            return new ReflectionClass($class);
        }
        throw new Exception("Don't know how to reflect $thing");
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
        echo (string) $this->getReflection($thing);
        var_dump($thing);
        $type = gettype($thing);
        if ($thing instanceof stdClass || $type == 'boolean' ||
            $type == 'integer') {
            return $this->_print($thing);
        }

        if (gettype($thing) == 'object') {
            $ro = new ReflectionObject($thing);
            echo $ro . "\n";
            return;
        }
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
        echo $this->getReflection($thing)->getDocComment();
    }
}

?>
