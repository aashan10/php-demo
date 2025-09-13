<?php

declare(strict_types=1);

namespace Elementary\Console;

use Elementary\Console\Components\Table;

abstract class Command
{
    // ANSI Color Codes
    protected const COLOR_DEFAULT = "\033[0m";
    protected const COLOR_GREEN = "\033[0;32m";
    protected const COLOR_RED = "\033[0;31m";
    protected const COLOR_YELLOW = "\033[1;33m";
    protected const COLOR_BLUE = "\033[0;34m"; // New color
    protected const COLOR_BOLD = "\033[1m";
    protected const COLOR_RESET_BOLD = "\033[22m";
    protected const COLOR_RESET_ALL = "\033[0m";

    public const SUCCESS = 0;
    public const FAILURE = 1;

    /**
     * Writes a string to the console.
     */
    public function line(string $text): void
    {
        echo $text . "\n";
    }

    /**
     * Writes a success message (green).
     */
    public function success(string $text): void
    {
        echo "\n" . self::COLOR_GREEN
             . self::COLOR_BOLD . "[SUCCESS]: " . self::COLOR_RESET_BOLD
             . $text . self::COLOR_RESET_ALL . "\n";
    }

    /**
     * Writes an informational message (blue).
     */
    public function info(string $text): void
    {
        echo "\n" . self::COLOR_BLUE
             . self::COLOR_BOLD . "[INFO]: " . self::COLOR_RESET_BOLD
             . $text . self::COLOR_RESET_ALL . "\n";
    }

    /**
     * Writes a warning message (yellow).
     */
    public function warning(string $text): void
    {
        echo "\n" . self::COLOR_YELLOW
             . self::COLOR_BOLD . "[WARNING]: " . self::COLOR_RESET_BOLD
             . $text . self::COLOR_RESET_ALL . "\n";
    }

    /**
     * Writes an error message (red).
     */
    public function error(string $text): void
    {
        echo "\n" . self::COLOR_RED
             . self::COLOR_BOLD . "[ERROR]: " . self::COLOR_RESET_BOLD
             . $text . self::COLOR_RESET_ALL . "\n";
    }

    /**
     * Renders a table to the console.
     */
    public function table(array $headers, array $rows): void
    {
        (new Table($headers, $rows))->display();
    }
}
