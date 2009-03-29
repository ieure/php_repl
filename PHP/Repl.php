#! @php_bin@
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
            fwrite($fp, "$k = $v\n");
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
        while (($__code__ = $this->read()) !== false) {
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
        static $implicit = array('return', 'throw', 'class', 'function',
                                 'interface', 'abstract', 'static',
                                 'include', 'include_once', 'require',
                                 'require_once', 'echo');

        if ($this->options['readline']) {
            $line = readline($this->options['prompt']);
            readline_add_history($line);
        } else {
            echo $this->options['prompt'];
            $line = fgets($this->input);
        }
        if (strlen($line) == 0) {
            return $line;
        }

        $line = trim($line);

        // Add a trailing semicolon
        if (substr($line, -1) != ';') {
            $line .= ';';
        }

        // Make sure we get a value back from eval()
        $first = substr($line, 0, strpos($line, " "));
        if (!in_array($first, $implicit)) {
            $line = 'return ' . $line;
        }
        return $line;
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
     * Dissect something
     *
     * @param mixed $thing The thing to dissect
     *
     * @return void
     */
    protected function dissect($thing)
    {
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

        switch (true) {
        case class_exists($thing, false):
            $rc = new ReflectionClass($thing);
            echo $rc . "\n";
            return;

        case function_exists($thing):
            $rm = new ReflectionFunction($thing);
            echo (string) $rm . "\n";
            return;

        default:
            break;
        }
    }
}

// If we're being run as a script, run the REPL.
if (php_sapi_name() == 'cli' &&
    basename($_SERVER['argv'][0]) == basename(__FILE__)) {
    $_repl = new PHP_Repl(array('autorun' => true));
}

?>
