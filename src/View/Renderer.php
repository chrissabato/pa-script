<?php

declare(strict_types=1);

namespace App\View;

class Renderer
{
    /** @var string */
    private $templatesPath;

    /** @var array<string, mixed> data every view gets (see share()) */
    private $shared = [];

    public function __construct(string $templatesPath)
    {
        $this->templatesPath = rtrim($templatesPath, '/');
    }

    /**
     * Data every view and the layout get, under their own data — e.g.
     * AdminGate's adminUnlocked for the nav.
     *
     * @param array<string, mixed> $data
     */
    public function share(array $data): void
    {
        $this->shared = array_merge($this->shared, $data);
    }

    public function render(string $template, array $data = []): string
    {
        $data = array_merge($this->shared, $data);
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

        return Url::to($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }

    /** The app's URL prefix, HTML-escaped: templates write href="<?= $this->base() ?>/games". */
    public function base(): string
    {
        return htmlspecialchars(Url::base(), ENT_QUOTES);
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
