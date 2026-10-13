<?php

declare(strict_types=1);

namespace App\Fill;

/**
 * Minimal Gemini API client (generateContent with a JSON response schema).
 * curl, not file_get_contents: production PHP has no https stream wrapper.
 */
class GeminiClient implements JsonModel
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    /**
     * Wall-clock budget for the whole call, retries included. PHP's
     * set_time_limit() doesn't count time spent waiting on curl or sleep(),
     * so without this a slow, overloaded Gemini kept the page loading for
     * minutes.
     */
    private const BUDGET_SECONDS = 90;
    /** Don't start a retry with less time than this left. */
    private const MIN_ATTEMPT_SECONDS = 20;

    /**
     * Flash-Lite with minimal thinking: on a real 25-player pronunciation
     * guide it gave the same suggestions as gemini-3.8-flash in ~3 s instead
     * of 1–3 minutes, which is too long to hold a web request open.
     */
    public const DEFAULT_MODEL = 'gemini-3.5-flash-lite';
    public const DEFAULT_THINKING_LEVEL = 'minimal';

    /** @var string */
    private $apiKey;
    /** @var string */
    private $model;
    /** @var string|null */
    private $thinkingLevel;

    /** @param string|null $thinkingLevel Gemini 3 thinkingLevel ("minimal", "low", …); null = the model's default */
    public function __construct(string $apiKey, string $model, ?string $thinkingLevel = null)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->thinkingLevel = $thinkingLevel;
    }

    /**
     * From .env: GEMINI_MODEL, and GEMINI_THINKING_LEVEL — which defaults to
     * minimal only for the default model, since not every model accepts it.
     *
     * @param array<string, mixed> $env
     */
    public static function fromEnv(array $env): self
    {
        $model = trim((string) ($env['GEMINI_MODEL'] ?? '')) ?: self::DEFAULT_MODEL;
        $level = trim((string) ($env['GEMINI_THINKING_LEVEL'] ?? ''));
        if ($level === '') {
            $level = $model === self::DEFAULT_MODEL ? self::DEFAULT_THINKING_LEVEL : null;
        }

        return new self((string) ($env['GEMINI_API_KEY'] ?? ''), $model, $level);
    }

    public function generateJson(array $parts, array $schema): array
    {
        if (!function_exists('curl_init')) {
            throw new DocumentFillException('The server is missing the PHP curl extension, so it cannot reach Gemini.');
        }

        $config = [
            'temperature' => 0,
            'responseMimeType' => 'application/json',
            'responseSchema' => $schema,
        ];
        if ($this->thinkingLevel !== null) {
            $config['thinkingConfig'] = ['thinkingLevel' => $this->thinkingLevel];
        }
        $body = json_encode([
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => $config,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Gemini answers 503/429 under load; those often pass in seconds.
        $deadline = time() + self::BUDGET_SECONDS;
        foreach ([0, 3, 8] as $attempt => $wait) {
            if ($attempt > 0 && $deadline - time() - $wait < self::MIN_ATTEMPT_SECONDS) {
                break;
            }
            sleep($wait);
            list($raw, $status, $error, $timedOut) = $this->post($body, $deadline - time());
            if ($status !== 503 && $status !== 429) {
                break;
            }
        }

        if ($timedOut) {
            throw new DocumentFillException('Gemini took too long to answer (it seems overloaded right now). '
                . 'Wait a few minutes and upload the document again.');
        }
        if ($raw === false) {
            throw new DocumentFillException('Couldn\'t reach Gemini: ' . $error);
        }
        $response = json_decode((string) $raw, true);
        if ($status === 503 || $status === 429) {
            throw new DocumentFillException('Gemini is busy right now. Wait a minute and upload the document again.');
        }
        if ($status !== 200) {
            $message = is_array($response) ? (string) ($response['error']['message'] ?? '') : '';
            throw new DocumentFillException('Gemini returned an error (HTTP ' . $status . ')' . ($message !== '' ? ': ' . $message : '.'));
        }

        $candidate = $response['candidates'][0] ?? null;
        $text = '';
        foreach ((array) ($candidate['content']['parts'] ?? []) as $part) {
            if (empty($part['thought']) && isset($part['text'])) {
                $text .= $part['text'];
            }
        }
        $json = json_decode($text, true);
        if (!is_array($json)) {
            $reason = (string) ($candidate['finishReason'] ?? ($response['promptFeedback']['blockReason'] ?? 'no answer'));
            throw new DocumentFillException('Gemini didn\'t return usable results (' . $reason . '). Try again, or try a PDF of the document.');
        }

        return $json;
    }

    /** @return array{0: string|false, 1: int, 2: string, 3: bool} body, HTTP status, curl error, timed out */
    private function post(string $body, int $timeoutSeconds): array
    {
        $curl = curl_init(sprintf(self::ENDPOINT, rawurlencode($this->model)));
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $this->apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
        ]);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $timedOut = curl_errno($curl) === CURLE_OPERATION_TIMEDOUT;
        curl_close($curl);

        return [$raw, $status, $error, $timedOut];
    }
}
