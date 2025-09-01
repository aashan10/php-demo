<?php

declare(strict_types=1);

namespace Elementary\Http;

use Elementary\Utils\ParameterBag;

class Response
{
    private int $statusCode;
    private string $content;
    private ?string $templatePath = null;
    private array $templateData = [];

    public ParameterBag $cookies;
    public ParameterBag $headers;


    public function __construct(int $statusCode = 200, string $content = '', array $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->content = $content;
        $this->cookies = new ParameterBag();
        $this->headers = new ParameterBag($headers);
    }

    public function setTemplate(string $path, array $data = []): self
    {
        $this->templatePath = $path;
        $this->templateData = $data;
        $this->content = ''; // Unset raw content if template is used.
        return $this;
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): string
    {
        $this->renderTemplate();
        return $this->content;
    }


    public function send(): void
    {
        $this->renderTemplate();

        http_response_code($this->statusCode);

        foreach ($this->headers->all() as $name => $value) {
            if (strtolower($name) === 'cookie') {
                throw new \InvalidArgumentException('Use the cookies property to set cookies.');
            } else {
                header("{$name}: {$value}");
            }
        }

        foreach ($this->cookies->all() as $name => $value) {
            setcookie($name, $value, time() + 3600, '/'); // Default to 1 hour expiration
        }

        echo $this->content;
    }

    private function renderTemplate(): void
    {
        if ($this->templatePath) {
            extract($this->templateData);

            ob_start();
            require $this->templatePath;
            $this->content = ob_get_clean();

            // Avoid re-rendering
            $this->templatePath = null;
        }
    }
}
