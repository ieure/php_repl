<?php

namespace PHPRepl;

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
class PHPRepl
{
    /**
     * Where we're reading input from
     *
     * @var resource
     */
    protected $input;

    /**
     * The options for this instance
     *
     * @var array
     */
    protected $options = array();

    protected $printers = array();

    /**
     * The path to the configuration file
     *
     * @var string
     */
    protected $rc_file;


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

        $this->printers      = $this->getDefaultPrinters();
        $defaults      = $this->getDefaultOptions();
        $this->options = array_merge_recursive($defaults, $options);

        $this->readline_support = true;
        if (!function_exists('readline') || getenv('TERM') == 'dumb') {
            $this->readline_support = false;
        }

        if ($this->readline_support &&
            is_readable($this->getOption('readline_hist'))) {
            readline_read_history($this->getOption('readline_hist'));
        }
    }

    public function getDefaultPrinters()
    {
        return array(
            'NULL'          => null,
            'double'        => 'var_dump',
            'float'         => 'var_dump',
            'integer'       => 'var_dump',
            'boolean'       => 'var_dump',
            'string'        => array('var_export',"\n"),
            'array'         => 'print_r',
            'object'        => 'print_r',
            '_default_'     => 'print_r',
        );
    }

    public function getPrinters()
    {
        return $this->printers;
    }

    public function setPrinter($type, $printer)
    {
        $this->printers[$type] = $printer;
    }

    public function getPrinter($type)
    {
        if (!isset($this->printers[$type])) {
            return $this->printers['_default_'];
        }

        return $this->printers[$type];
    }

    /**
     * Get default options
     *
     * @return array Defaults
     */
    public function getDefaultOptions()
    {
        $defaults = array(
            'prompt'        => 'php> ',
            'showtime'      => false,
            'readline_hist' => getenv('HOME') . '/.phprepl_history',
        );

        if (is_readable($this->rc_file)) {
            $defaults = array_merge($defaults, parse_ini_file($this->rc_file));
        }
        return $defaults;
    }

    /**
     * Set options
     *
     * @param array $options An array of options for use in the REPL
     *
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOption($type, $option)
    {
        $this->options[$type] = $option;
    }

    public function getOption($type)
    {
        if (!isset($this->options[$type])) {
            return null;
        }

        return $this->options[$type];
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        fclose($this->input);
        if ($this->readline_support) {
            readline_write_history($this->getOption('readline_hist'));
        }

        // Save config
        /* turning off for now (maybe permanently) as support for nested
         * arrays is broken
         *
        $fp = fopen($this->rc_file, 'w');
        if ($fp === false) {
            return;
        }
        foreach ($this->options as $k => $v) {
            fwrite($fp, "$k = \"$v\"\n");
        }
        fclose($fp);
         */
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
        ob_implicit_flush(true);
        error_reporting(E_ALL | E_STRICT);
        ini_set('html_errors', 'Off');
        ini_set('display_errors', 'On');
        extract($scope);
        ob_start();
        while (true) {
            // inner loop is to escape from stacked output buffers
            while ($__ob__ = ob_get_clean()) {
                echo $this->obCleanup($__ob__);
                unset($__ob__);
            }

            try {
                if (((boolean) $__code__ = $this->read()) === false) {
                    continue;
                }
                ob_start(array($this, 'obCleanup'));

                $this->output($_ = eval($this->cleanup($__code__)));
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
        $stack = array();
        static $shifted;
        if (!$shifted) {
            // throw away argv[0]
            array_shift($_SERVER['argv']);
            $shifted = true;
        }
        do {
            $prompt = $lines > 0 ? '> ' : ($this->getOption('showtime') ? date('G:i:s ') : '') . $this->getOption('prompt');
            if (count($_SERVER['argv'])) {
                $line = array_shift($_SERVER['argv']);
            } elseif ($this->readline_support) {
                $line = readline($prompt);
            } else {
                echo $prompt;
                $line = fgets($this->input);
            }

            // If the input was empty, return false; this breaks the loop.
            if ($line === false) {
                return false;
            }

            $done = true;
            $line = trim($line);
            // If the last char is a backslash, remove it and
            // accumulate more lines.
            if (substr($line, -1) == '\\') {
                $line = trim(substr($line, 0, strlen($line) - 1));
                $done = false;
            }

            // check for curleys and parens, keep accumulating lines.
            $tokens = token_get_all("<?php {$line}");
            foreach ($tokens as $t) {
                switch ($t) {
                    case '{':
                    case '(':
                        array_push($stack, $t);
                        break;

                    case '}':
                        if ('{' !== array_pop($stack)) {
                            throw new \Exception('Unmatched closing brace.');
                        }
                        break;
                    case ')':
                        if ('(' !== array_pop($stack)) {
                            throw new \Exception('Unmatched closing paren.');
                        }
                        break;
                }
            }
            if (count($stack) > 0) {
                $last_t = array_pop($tokens);
                if (is_array($last_t) && $last_t[0] == T_OPEN_TAG) {
                    // if the last token was an open tag, this is nothing.
                } elseif ($stack[count($stack) - 1] === '{' && !in_array($last_t, array('{', '}', ';'))) {
                    // allow implied semicolons inside curlies
                    $line .= ';';
                }
                $done = false;
            }
            $code .= $line;
            $lines++;
        } while (!$done);

        // Add the whole block to the readline history.
        if ($this->readline_support) {
            readline_add_history($code);
            readline_write_history($this->getOption('readline_hist'));
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

            $tokens = token_get_all("<?php {$input}");

            // if the input string contains anything but a single variable,
            // wrap it in single-quotes
            if (!(count($tokens) == 2 && isset($tokens[1][0]) && $tokens[1][0] == T_VARIABLE)) {
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
    public function obCleanup($output)
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
    protected function output($out)
    {
        $printer = $this->getPrinter(gettype($out));
        $extra = '';

        if (!$printer) {
            return;
        }

        if (is_array($printer)) {
            $extras = $printer;
            $printer = array_shift($extras);
            $extra = implode('', $extras);
        }

        call_user_func($printer, $out);
        echo $extra;
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
                return new \ReflectionObject($thing);

            case class_exists($thing, false):
                return new \ReflectionClass($thing);

            case function_exists($thing):
                return new \ReflectionFunction($thing);

            case strstr($thing, '::'):
                list($class, $what) = explode('::', $thing);
                $rc = new \ReflectionClass($class);

                switch (true) {
                    case substr($what, -2) == '()':
                        $what = substr($what, 0, strlen($what) - 2);
                        // fallthrough
                    case $rc->hasMethod($what):
                        return $rc->getMethod($what);

                    case substr($what, 0, 1) == '$':
                        $what = substr($what, 1);
                        // fallthrough
                    case $rc->hasProperty($what):
                        return $rc->getProperty($what);

                    case $rc->hasConstant($what):
                        return $rc->getConstant($what);
                }
                // fallthrough

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
            echo "{$meth->getName()}()\n";
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
            echo preg_replace(
                '/^\s*\*/m',
                ' *',
                $r->getDocComment()
            ) . "\n";
        } else {
            echo "(no doc)\n";
        }
        return "---";
    }
}
