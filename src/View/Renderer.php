<?php

declare(strict_types=1);

namespace App\View;

class Renderer
{
    /** @var string */
    private $templatesPath;

    public function __construct(string $templatesPath)
    {
        $this->templatesPath = rtrim($templatesPath, '/');
    }

    public function render(string $template, array $data = []): string
    {
        $content = $this->renderTemplate($template, $data);

        return $this->renderTemplate('layout', array_merge($data, ['content' => $content]));
    }

    /**
     * A public/ asset URL versioned by its modification time, so browsers
     * pick up edited JS/CSS immediately instead of reusing a cached copy.
     * Templates call it as $this->asset('/js/foo.js').
     */
    public function asset(string $path): string
    {
        $file = dirname(__DIR__, 2) . '/public' . $path;

        return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
    }

    private function renderTemplate(string $template, array $data): string
    {
        $file = $this->templatesPath . '/' . $template . '.phtml';

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
