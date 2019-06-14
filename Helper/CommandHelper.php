<?php
/**
 * Created by PhpStorm.
 * File: CommandHelper.php
 * User: Conrad
 * Date: 02.10.2014
 * Time: 19:30
 */

namespace SN\ToolboxBundle\Helper;


use SN\ToolboxBundle\Exception\MissingParameterException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class CommandHelper
{

    protected static $headlineCounter = 0;

    /**
     * @var bool
     */
    protected static $fancyBorder = true;

    /*
     * writes a nice headline
     *
     * @param OutputInterface $output
     * @param $title
     * @param string $style - put a %s inside the format tags to place the content inside <fg=black;bg=cyan>%s</fg=black;bg=cyan>
     * @param boolean $fancyBorder
     */
    public static function writeHeadline(
        OutputInterface $output,
        $title,
        $style = '<fg=white>%s</fg=white>',
        $fancyBorder = null
    )
    {
        self::$headlineCounter++;

        // give the title some extra whitespace and the counter..
        $title = sprintf(' %s: %s ', self::$headlineCounter, $title);

        $writeChar = function ($char, $length) use ($output, $style) {
            $chars = '';
            for ($i = 0; $i < $length; $i++) {
                $chars .= $char;
            }
            $output->write(sprintf($style, $chars));
        };

        $fancyBorder = is_bool($fancyBorder) ? $fancyBorder : self::$fancyBorder;

        if ($fancyBorder) {
            $border = [
                'top-left'     => '╔',
                'bottom-left'  => '╚',
                'top-right'    => '╗',
                'bottom-right' => '╝',
                'left'         => '║',
                'right'        => '║',
                'horizontal'   => '═',
            ];
        } else {
            $border = [
                'top-left'     => '+',
                'bottom-left'  => '+',
                'top-right'    => '+',
                'bottom-right' => '+',
                'left'         => '|',
                'right'        => '|',
                'horizontal'   => '-',
            ];
        }

        $output->writeln('');
        $output->write(sprintf($style, $border['top-left']));
        $writeChar($border['horizontal'], strlen(strip_tags($title)));
        $output->write(sprintf($style, $border['top-right']));
        $output->writeln('');
        $output->writeln(sprintf($style, sprintf($border['left'] . '%s' . $border['right'], $title)));
        $output->write(sprintf($style, $border['bottom-left']));
        $writeChar($border['horizontal'], strlen(strip_tags($title)));
        $output->write(sprintf($style, $border['bottom-right']));
        $output->writeln('');
    }

    /**
     * clears the last line after a countdown
     *
     * @param OutputInterface $output
     */
    public static function clearLineAfterCountdown(OutputInterface $output)
    {
        $output->write("\x0D");
        $output->write('                ');
        $output->writeln('');
    }

    /**
     * @param string $text
     * @return string
     */
    public static function writeSuccess($text)
    {
        return sprintf('<info>%s</info>', $text);
    }

    /**
     * @param string $text
     * @return string
     */
    public static function writeError($text)
    {
        return sprintf('<fg=red>%s</fg=red>', $text);
    }

    /**
     * @param string $text
     * @return string
     */
    public static function writeWarning($text)
    {
        return sprintf('<fg=yellow>%s</fg=yellow>', $text);
    }

    /**
     * countdown to seconds
     *
     * @param OutputInterface $output
     * @param int $seconds
     */
    public static function countdown(OutputInterface $output, $seconds = 5)
    {
        if ($seconds > 0) {
            while ($seconds > 0) {
                // replace current line
                $output->write("\x0D");
                $output->write(sprintf('%s Seconds', $seconds));
                $seconds--;
                usleep(1000000);
            }
            self::clearLineAfterCountdown($output);
        }
    }

    /**
     * @param OutputInterface $output
     * @param String $text
     */
    public static function replaceInline(OutputInterface $output, $text)
    {
        $output->write("\x0D");
        $output->write($text);
    }

    /**
     * @return boolean
     */
    public static function isFancyBorder()
    {
        return self::$fancyBorder;
    }

    /**
     * @param boolean $fancyBorder
     */
    public static function setFancyBorder($fancyBorder)
    {
        self::$fancyBorder = $fancyBorder;
    }

    /**
     * @param OutputInterface $output
     * @param \Exception $e
     */
    public static function writeException(OutputInterface $output, \Exception $e)
    {
        self::writeHeadline($output, $e->getMessage());
        $output->writeln(sprintf('Line %o', $e->getLine()));
        $output->writeln(sprintf('File %o', $e->getFile()));
        $output->writeln(sprintf('Trace', $e->getFile()));

        foreach ($e->getTrace() as $key => $trace) {
            $output->writeln($key);
            if (array_key_exists('file', $trace)) {
                $output->writeln(sprintf('  File:     %s', (string)$trace['file']));
            }
            if (array_key_exists('line', $trace)) {
                $output->writeln(sprintf('  Line:     %s', (string)$trace['line']));
            }
            if (array_key_exists('function', $trace)) {
                $output->writeln(sprintf('  Function: %s', (string)$trace['function']));
            }
            if (array_key_exists('class', $trace)) {
                $output->writeln(sprintf('  Class:    %s', (string)$trace['class']));
            }
            if (array_key_exists('type', $trace)) {
                $output->writeln(sprintf('  Type:     %s', (string)$trace['type']));
            }
            if (array_key_exists('args', $trace)) {
                $output->writeln(
                    sprintf(
                        '  args:     %s',
                        implode(', ', StringHelper::transformToArrayString((array)$trace['args']))
                    )
                );
            }
            $output->writeln('');
        }
    }

    /**
     * @param $command
     * @param array $options
     * @return string
     */
    public static function execute($command, array $options = array())
    {
        $options = array_merge([
            "output"              => null,
            "command_description" => null,
            "print_output"        => false,
            "timeout"             => 3600,
            "idle_timeout"        => 600
        ],
            $options);

        foreach ($options as $k => $v) {
            switch ($k) {
                case 'output':
                    if (false === ($v instanceof OutputInterface || null === $v)) {
                        throw new \InvalidArgumentException(sprintf("output has to be OutputInterface or null"));
                    }
                    break;
                case 'command_description':
                    if (false === (true === is_string($v) || null === $v)) {
                        throw new \InvalidArgumentException(sprintf("command_description has to be String or null"));
                    }
                    break;
                case 'print_output':
                    if (false === (true === is_bool($v))) {
                        throw new \InvalidArgumentException(sprintf("print_output has to be Boolean"));
                    }
                    break;
                case 'timeout':
                    if (false === is_int($v)) {
                        throw new \InvalidArgumentException(sprintf("timeout has to be Integer"));
                    }
                    break;
                case 'idle_timeout':
                    if (false === is_int($v)) {
                        throw new \InvalidArgumentException(sprintf("idle_timeout has to be Integer"));
                    }
                    break;
            }
        }

        $process = new Process($command);
        $process->setTimeout($options['timeout']);
        $process->setIdleTimeout($options['idle_timeout']);

        if (false === ($options["output"] instanceof OutputInterface)) {
            $process->run();

            return trim($process->getOutput());
        }

        if (true === is_string($options["command_description"])) {
            if (false === $options["output"]->isVerbose()) {
                $options["output"]->writeln($options["command_description"]);
            } elseif (false === $options["print_output"]) {
                $cmdLoader = new CommandLoader($options["output"]);
                $cmdLoader->setMessage($options["command_description"]);
                $cmdLoader->run();
            }
        } else {
            $options["output"]->writeln(sprintf("<info>%s</info>", $command));
        }

        if (true === $options["print_output"]) {
            $process->run(
                function ($type, $buffer) use ($options) {
                    if (($options["output"] instanceof OutputInterface) === true) {
                        $options["output"]->write($buffer);
                    }
                }
            );
        } else {
            $process->run();
        }

        if (true === is_string($options["command_description"]) && true === $options["output"]->isVerbose()) {
            $cmdLoader->stop("");
            $options["output"]->writeln($options["command_description"]);
        }

        return trim($process->getOutput());

    }

    /**
     * @param $command
     * @param OutputInterface|null $output
     * @param bool|string $printOutput
     * @return string
     * @deprecated Please use CommandHelper::execute
     */
    public static function executeCommand($command, OutputInterface $output = null, $printOutput = true)
    {
        return self::execute($command,
            array(
                "output"              => $output,
                "command_description" => (is_string($printOutput)) ? $printOutput : null,
                "print_output"        => (is_bool($printOutput)) ? $printOutput : false
            ));
    }

    /**
     * @param OutputInterface $output
     * @param array $remote
     * @param array $local
     * @param string $titleRemote
     * @param string $titleLocal
     * @throws MissingParameterException
     */
    public static function compareParametersYml(
        OutputInterface $output,
        array $remote,
        array $local,
        $titleRemote = 'Remote',
        $titleLocal = 'Local'
    )
    {
        if (!isset($remote['parameters']) || !is_array($remote['parameters'])) {
            throw new \InvalidArgumentException(sprintf('Remote Array needs to have a [parameters] array'));
        }
        if (!isset($local['parameters']) || !is_array($local['parameters'])) {
            throw new \InvalidArgumentException(sprintf('Remote Array needs to have a [parameters] array'));
        }

        $remoteParam = $remote['parameters'];
        $localParam  = $local['parameters'];

        $missingLocal  = array();
        $missingRemote = array();
        $remoteTypes   = array();
        $localTypes    = array();

        foreach ($remoteParam as $key => $value) {
            if (array_key_exists($key, $localParam) === false) {
                $missingLocal[$key] = $value;
            } else {
                $remoteTypes[$key] = array(
                    'value' => $value,
                    'type'  => gettype($value),
                );
            }
        }

        foreach ($localParam as $key => $value) {
            if (array_key_exists($key, $remoteParam) === false) {
                $missingRemote[$key] = $value;
            } else {
                $localTypes[$key] = array(
                    'value' => $value,
                    'type'  => gettype($value),
                );
            }
        }

        $typeTable = new Table($output);
        $typeTable->setHeaders(
            array(
                'Param',
                sprintf('Type [%s]', $titleRemote),
                sprintf('Value [%s]', $titleRemote),
                sprintf('Type [%s]', $titleLocal),
                sprintf('Value [%s]', $titleLocal),
            )
        );
        $typeTableHasRows = false;
        foreach ($remoteTypes as $key => $value) {
            if (array_key_exists($key, $localTypes) && $value['type'] !== $localTypes[$key]['type']) {
                $typeTableHasRows = true;
                $typeTable->addRow(
                    array(
                        $key,
                        $value['type'],
                        $value['value'],
                        $localTypes[$key]['type'],
                        $localTypes[$key]['value'],
                    )
                );
            }
        }
        if ($typeTableHasRows) {
            CommandHelper::writeHeadline($output, 'Parameter Type Missmatch', '<fg=yellow>%s</fg=yellow>');
            self::setTableColor($typeTable);
            $typeTable->render();
        }

        if (empty($missingLocal) && empty($missingRemote)) {
            return;
        }

        if (!empty($missingLocal)) {
            $missingLocalTable = new Table($output);
            $missingLocalTable->setHeaders(array('Param Name', sprintf('[%s] Value', $titleRemote)));
            foreach ($missingLocal as $key => $value) {
                if (is_array($value)) {
                    $value = sprintf('[%s]', implode(',', $value));
                }
                $missingLocalTable->addRow(array($key, is_null($value) ? 'NULL' : $value));
            }
            self::writeHeadline($output, sprintf('Missing [%s] Params:', $titleLocal));
            $missingLocalTable->render();
        }

        if (!empty($missingRemote)) {
            $missingRemoteTable = new Table($output);
            $missingRemoteTable->setHeaders(array('Param Name', sprintf('[%s] Value', $titleLocal)));
            foreach ($missingRemote as $key => $value) {
                if (is_array($value)) {
                    $value = sprintf('[%s]', implode(',', $value));
                }
                $missingRemoteTable->addRow(array($key, is_null($value) ? 'NULL' : $value));
            }
            self::writeHeadline($output, sprintf('Missing [%s] Params:', $titleRemote));
            $missingRemoteTable->render();

            throw new MissingParameterException(
                sprintf('[%s] Parameters missing. Please fix and try again', $titleRemote)
            );
        }

    }

    /**
     * @param Table $table
     * @param string $style - uses sprintf to include table border chars
     */
    public static function setTableColor(Table $table, $style = '<fg=yellow>%s</>')
    {
        // by default, this is based on the default style
        $tableStyle = new TableStyle();
        $tableStyle
            ->setHorizontalBorderChar(sprintf($style, '-'))
            ->setVerticalBorderChar(sprintf($style, '|'))
            ->setCrossingChar(sprintf($style, '+'));

        // use the style for this table
        $table->setStyle($tableStyle);
    }

    /**
     * @param String $cmd
     * @param array $config
     * @param OutputInterface $output
     * @param bool $write
     * @throws MissingParameterException
     */
    public static function executeRemoteCommand($cmd, array $config, OutputInterface $output = null, $write = true)
    {
        if (empty($config["user"]) === true) {
            throw new MissingParameterException(
                sprintf('[user] Parameters missing in $config array.')
            );
        }

        if (empty($config["host"]) === true) {
            throw new MissingParameterException(
                sprintf('[host] Parameters missing in $config array.')
            );
        }

        if (empty($config["port"]) === true) {
            $config["port"] = 22;
        }

        if (empty($config["webroot"]) === true) {
            $config["webroot"] = "./";
        }

        $cmd = sprintf(
            'ssh %s@%s -p %s "cd %s; %s"',
            $config["user"],
            $config["host"],
            $config["port"],
            $config["webroot"],
            addslashes($cmd)
        );

        return self::executeCommand($cmd, $output, $write);
    }
}
