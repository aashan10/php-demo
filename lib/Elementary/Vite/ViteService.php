<?php

declare(strict_types=1);

namespace Elementary\Vite;

use Elementary\Config\ConfigBag;

final class ViteService
{
    private array $manifest = [];
    private bool $isDev;
    private string $manifestPath;
    private string $viteServer;

    public function __construct(private ConfigBag $config)
    {
        $this->isDev = false;//str_starts_with($this->config->get('app.env', 'prod'), 'dev');
        $this->manifestPath = BASE_PATH . '/public/build/.vite/manifest.json';
        $this->viteServer = 'http://localhost:5173';

        if (!$this->isDev) {
            $this->loadManifest();
        }
    }

    private function loadManifest(): void
    {
        if (file_exists($this->manifestPath)) {
            $this->manifest = json_decode(file_get_contents($this->manifestPath), true);
        } else {
            throw new \RuntimeException("Vite manifest file not found at {$this->manifestPath}");
        }
    }

    public function getAssetPaths(string $entrypoint): string
    {
        if ($this->isDev) {
            return $this->getDevScriptTags($entrypoint);
        }

        return $this->getProdAssetTags($entrypoint);
    }

    private function getDevScriptTags(string $entrypoint): string
    {
        $scripts = "<script type=\"module\" src=\"{$this->viteServer}/@vite/client\"></script>";
        $scripts .= "<script type=\"module\" src=\"{$this->viteServer}/{$entrypoint}\"></script>";
        return $scripts;
    }

    private function getProdAssetTags(string $entrypoint): string
    {
        if (empty($this->manifest[$entrypoint])) {
            return '';
        }

        $tags = '';
        $entry = $this->manifest[$entrypoint];


        // Add the main JS file
        $tags .= '<script type="module" src="/build/' . $entry['file'] . '"></script>';

        // Add any CSS files associated with the entry point
        if (!empty($entry['css'])) {
            foreach ($entry['css'] as $cssFile) {
                $tags .= '<link rel="stylesheet" href="/build/' . $cssFile . '">';
            }
        }

        return $tags;
    }
}
