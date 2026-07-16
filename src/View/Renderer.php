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

    private function renderTemplate(string $template, array $data): string
    {
        $file = $this->templatesPath . '/' . $template . '.phtml';

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
