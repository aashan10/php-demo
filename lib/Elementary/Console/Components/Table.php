<?php

declare(strict_types=1);

namespace Elementary\Console\Components;

class Table
{
    private array $headers;
    private array $rows;
    private array $columnWidths = [];

    public function __construct(array $headers, array $rows)
    {
        $this->headers = $headers;
        $this->rows = $rows;
        $this->calculateColumnWidths();
    }

    public function display(): void
    {
        $this->printLine();
        $this->printHeader();
        $this->printLine();
        $this->printRows();
        $this->printLine();
    }

    private function calculateColumnWidths(): void
    {
        foreach ($this->headers as $index => $header) {
            $this->columnWidths[$index] = strlen($header);
        }

        foreach ($this->rows as $row) {
            foreach ($row as $index => $cell) {
                if (strlen((string)$cell) > ($this->columnWidths[$index] ?? 0)) {
                    $this->columnWidths[$index] = strlen((string)$cell);
                }
            }
        }
    }

    private function printLine(): void
    {
        echo "+";
        foreach ($this->columnWidths as $width) {
            echo str_repeat('-', $width + 2) . "+";
        }
        echo "\n";
    }

    private function printHeader(): void
    {
        $boldBlue = "\033[1;34m";
        $reset = "\033[0m";

        echo "|";
        foreach ($this->headers as $index => $header) {
            $paddedHeader = str_pad($header, $this->columnWidths[$index]);
            echo " " . $boldBlue . $paddedHeader . $reset . " |";
        }
        echo "\n";
    }

    private function printRows(): void
    {
        foreach ($this->rows as $row) {
            echo "|";
            foreach ($row as $index => $cell) {
                echo " " . str_pad((string)$cell, $this->columnWidths[$index] + 1) . "|";
            }
            echo "\n";
        }
    }
}
