<?php

declare(strict_types=1);

namespace Elementary\Log\Drivers;

use Stringable;

class FileLogger extends AbstractDriver
{
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->appendFile(
            path: $this->getLogFilePathFor('emergency'), 
            message: sprintf("[%s] [EMERGENCY] - %s [CONTEXT]: %s\n", date('Y-m-d H:i:s'), $message, json_encode($context))
        );
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->appendFile(
            path: $this->getLogFilePathFor('alert'), 
            message: sprintf("[%s] [ALERT] - %s [CONTEXT]: %s\n", date('Y-m-d H:i:s'), $message, json_encode($context))
        );
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->appendFile(
            path: $this->getLogFilePathFor('critical'), 
            message: sprintf("[%s] [CRITICAL] - %s [CONTEXT]: %s\n", date('Y-m-d H:i:s'), $message, json_encode($context))
        );
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->appendFile(
            path: $this->getLogFilePathFor('error'), 
            message: sprintf("[%s] [ERROR] - %s [CONTEXT]: %s\n", date('Y-m-d H:i:s'), $message, json_encode($context))
        );
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->appendFile(
            path: $this->getLogFilePathFor('warning'), 
            message: sprintf("[%s] [WARNING] - %s [CONTEXT]: %s\n", date('Y-m-d H:i:s'), $message, json_encode($context))
        );
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->appendFile(
            path: $this->getLogFilePathFor('notice'), 
            message: sprintf("[%s] [NOTICE] - %s [CONTEXT]: %s\n", date('Y-m-d H:i:s'), $message, json_encode($context))
        );
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->appendFile(
            path: $this->getLogFilePathFor('info'), 
            message: sprintf("[%s] [INFO] - %s [CONTEXT]: %s\n", date('Y-m-d H:i:s'), $message, json_encode($context))
        );
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->appendFile(
            path: $this->getLogFilePathFor('debug'), 
            message: sprintf("[%s] [DEBUG] - %s [CONTEXT]: %s\n", date('Y-m-d H:i:s'), $message, json_encode($context))
        );
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->appendFile(
            path: $this->getLogFilePathFor($level), 
            message: sprintf("[%s] [LOG] - %s [CONTEXT]: %s\n", date('Y-m-d H:i:s'), $message, json_encode($context))
        );
    }

    private function appendFile(string $path, string $message): void
    {
        $handle = fopen($path, 'a');
        fwrite($handle, $message);
        fclose($handle);
    }

    protected function initialize(): void 
    {
        $fileConfig = $this->config->get('file', []);
        $logPath = rtrim($fileConfig['path'] ?? '', '/') . '/';
        $levels = $fileConfig['levels'] ?? [];

        foreach ($levels as $level => $fileName) {
            if ($fileName === null) {
                continue;
            }
            $this->ensureFileExists($logPath . $fileName);
        }
    }

    private function getLogFilePathFor(string $level): string 
    {
        $path = $this->config->get('logging.file.path');
        $file = $this->config->get(sprintf('logging.file.levels.%s', $level));

        return sprintf("%s/%s", rtrim($path, '/'), ltrim($file, '/'));
    }

    private function ensureFileExists(string $filePath): void 
    {
        if (file_exists($filePath)) {
            return;
        }

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        touch($filePath);
    }
}
