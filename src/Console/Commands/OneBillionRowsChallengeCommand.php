<?php

declare(strict_types=1);

namespace App\Console\Commands;

final class OneBillionRowsChallengeCommand 
{
    public static string $defaultName = '1brc:execute'; // New property

    private string $filePath = BASE_PATH . '/data/measurements.txt';
    public function execute(array $args): int 
    {

        $map = [];

        if (isset( $args[0] )) {
            $this->filePath = $args[0];
        }

        foreach ($this->getRow() as $row) {
            [$city, $temperature] = $row;

            if (isset($map[$city])) {
                $map[$city]['sum'] += (float)$temperature;
                $map[$city]['count'] += 1;
            } else {
                $map[$city] = [
                    'sum' => (float)$temperature,
                    'count'=> 1
                ];
            }

        }


        foreach ($map as $city => $data) {
            $average = $data['sum'] / $data['count'];
            echo sprintf("%s: %.2f; %d\n", $city, $average, $data['count']);
        }


        return 0;
    }

    private function getRow(): \Generator
    {
        $file = fopen($this->filePath, 'r');

        while (($line = fgets(stream: $file)) !== false) {
            yield str_getcsv( $line, ';', escape: '\\' );
        }
    }
}
