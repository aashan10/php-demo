<?php

declare(strict_types=1);

namespace App\Console\Commands;

/**
 * A high-performance, parallel processing command for the One Billion Rows Challenge.
 *
 * This script uses the pcntl extension to fork multiple worker processes,
 * allowing it to process chunks of the file in parallel to leverage multiple CPU cores.
 */
final class OneBillionRowsChallengeForkCommand 
{
    public static string $defaultName = '1brc:fork'; // New property

    private string $filePath = BASE_PATH . '/data/measurements.txt';
    private int $numWorkers;

    public function __construct()
    {
        // Determine the number of available CPU cores to use as workers (cross-platform).
        if (PHP_OS_FAMILY === 'Darwin') { // macOS
            $this->numWorkers = (int) shell_exec('sysctl -n hw.ncpu') ?: 4;
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $this->numWorkers = (int) shell_exec('nproc') ?: 4;
        } else {
            // Fallback for other OSes (like Windows), though pcntl is not available there.
            $this->numWorkers = 4;
        }    
    }

    /**
     * Executes the main command logic.
     *
     * @param array $args Command-line arguments.
     * @return int Exit code.
     */
    public function execute(array $args): int
    {
        if (!extension_loaded('pcntl')) {
            echo "Error: The 'pcntl' extension is required for parallel processing but is not enabled.\n";
            return 1;
        }

        if (isset($args[0])) {
            $this->filePath = $args[0];
        }

        if (!file_exists($this->filePath)) {
            echo "Error: File not found at '{$this->filePath}'\n";
            return 1;
        }

        $fileSize = filesize($this->filePath);
        $chunkSize = (int) ceil($fileSize / $this->numWorkers);
        $pids = [];
        $tmpFiles = [];

        // Fork worker processes
        for ($i = 0; $i < $this->numWorkers; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                die("Could not fork process\n");
            }

            if ($pid) {
                // Parent process
                $pids[] = $pid;
            } else {
                // Child worker process
                $start = $i * $chunkSize;
                $end = min($start + $chunkSize, $fileSize);
                $outputFile = sys_get_temp_dir() . '/1brc_chunk_' . getmypid() . '.tmp';
                $this->processChunk($start, $end, $outputFile);
                exit(0);
            }
        }

        // Wait for all child processes to complete
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $tmpFiles[] = sys_get_temp_dir() . '/1brc_chunk_' . $pid . '.tmp';
        }

        // Aggregate results from all workers
        $finalResults = $this->aggregateResults($tmpFiles);

        // Sort and print the final output
        ksort($finalResults, SORT_STRING);
        $this->printResults($finalResults);

        return 0;
    }

    /**
     * Processes a specific chunk of the file. Executed by each worker.
     *
     * @param int $start The byte offset to start reading from.
     * @param int $end The byte offset to stop reading at.
     * @param string $outputFile The temporary file to write partial results to.
     */
    private function processChunk(int $start, int $end, string $outputFile): void
    {
        $handle = fopen($this->filePath, 'r');
        fseek($handle, $start);

        // If not the first chunk, discard the first partial line
        if ($start > 0) {
            fgets($handle);
        }

        $stations = [];
        while (ftell($handle) < $end && ($line = fgets($handle)) !== false) {
            $parts = str_getcsv($line, ';', escape: '\\');
            if (count($parts) !== 2) continue;

            $city = $parts[0];
            $temp = (float)$parts[1];

            if (!isset($stations[$city])) {
                $stations[$city] = ['min' => $temp, 'max' => $temp, 'sum' => $temp, 'count' => 1];
            } else {
                $stationData = & $stations[$city];
                $stationData['sum'] += $temp;
                $stationData['count']++;
                if ($temp < $stationData['min']) $stationData['min'] = $temp;
                if ($temp > $stationData['max']) $stationData['max'] = $temp;
            }
        }
        fclose($handle);

        // Write the partial result to a temporary file
        file_put_contents($outputFile, serialize($stations));
    }

    /**
     * Aggregates the results from all worker temporary files.
     *
     * @param array $tmpFiles An array of paths to temporary result files.
     * @return array The final aggregated results.
     */
    private function aggregateResults(array $tmpFiles): array
    {
        $aggregated = [];
        foreach ($tmpFiles as $file) {
            $chunkData = unserialize(file_get_contents($file));
            foreach ($chunkData as $city => $data) {
                if (!isset($aggregated[$city])) {
                    $aggregated[$city] = $data;
                } else {
                    $aggData = & $aggregated[$city];
                    $aggData['sum'] += $data['sum'];
                    $aggData['count'] += $data['count'];
                    if ($data['min'] < $aggData['min']) $aggData['min'] = $data['min'];
                    if ($data['max'] > $aggData['max']) $aggData['max'] = $data['max'];
                }
            }
            unlink($file); // Clean up the temporary file
        }
        return $aggregated;
    }

    /**
     * Prints the final results in the required format.
     *
     * @param array $results The final, sorted results.
     */
    private function printResults(array $results): void
    {
        $output = '{';
        $first = true;
        foreach ($results as $city => $data) {
            if (!$first) {
                $output .= ', ';
            }
            $mean = $data['sum'] / $data['count'];
            $output .= sprintf("%s=%.1f/%.1f/%.1f\n", $city, $data['min'], $mean, $data['max']);
            $first = false;
        }
        $output .= "}\n";
        echo $output;
    }
}