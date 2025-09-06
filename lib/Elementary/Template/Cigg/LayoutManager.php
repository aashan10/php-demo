<?php

declare(strict_types=1);

namespace Elementary\Template\Cigg;

/**
 * Manages template inheritance (extends, section, yield).
 */
class LayoutManager
{
    private ?string $layout = null;
    private array $sections = [];
    private array $sectionStack = [];
    private bool $isRenderingLayout = false;

    /**
     * Sets the layout to be extended.
     */
    public function extend(string $layout): void
    {
        if ($this->layout === null) {
            $this->layout = $layout;
        }
    }

    /**
     * Gets the current layout.
     */
    public function getLayout(): ?string
    {
        return $this->layout;
    }

    /**
     * Clears the layout so the parent template doesn't also try to extend a layout.
     */
    public function clearLayout(): void
    {
        $this->layout = null;
    }

    /**
     * Starts capturing a section.
     */
    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    /**
     * Stops capturing a section.
     */
    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            ob_end_clean();
            return;
        }
        $this->sections[$name] = ob_get_clean();
    }

    /**
     * Yields the content of a section.
     */
    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Resets the state for a new rendering cycle.
     */
    public function reset(): void
    {
        $this->layout = null;
        $this->sections = [];
        $this->sectionStack = [];
        $this->isRenderingLayout = false;
    }

    public function setIsRenderingLayout(bool $isRendering): void
    {
        $this->isRenderingLayout = $isRendering;
    }

    public function isRenderingLayout(): bool
    {
        return $this->isRenderingLayout;
    }
}