<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Chat;

use Akawaka\Bridge\Copilot\Copilot;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Metadata\TokenUsage;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

final class ResultConverter implements ResultConverterInterface
{
    private const STREAM_FINISH_MESSAGE = '[DONE]';

    public function supports(Model $model): bool
    {
        return $model instanceof Copilot;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();
        $statusCode = $response->getStatusCode();

        if (401 === $statusCode) {
            $error = $this->extractErrorMessage($response);

            throw new AuthenticationException($error ?? 'GitHub Copilot rejected the provided access token.');
        }

        if (429 === $statusCode) {
            throw new RateLimitExceededException();
        }

        if ($statusCode >= 400) {
            $error = $this->extractErrorMessage($response);

            throw new RuntimeException($error ?? sprintf('Unexpected response status %d from GitHub Copilot.', $statusCode));
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($response));
        }

        $data = $result->getData();

        if (!isset($data['choices']) || !is_array($data['choices'])) {
            throw new RuntimeException('GitHub Copilot returned an unexpected response payload.');
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        $result = 1 === count($choices) ? $choices[0] : new ChoiceResult(...$choices);

        return $this->attachMetadata($result, $data);
    }

    private function convertStream(HttpResponse $response): \Generator
    {
        $client = new EventSourceHttpClient();
        $toolCalls = [];

        foreach ($client->stream($response) as $chunk) {
            if (!$chunk instanceof ServerSentEvent) {
                continue;
            }

            if (self::STREAM_FINISH_MESSAGE === $chunk->getData()) {
                if ($toolCalls !== []) {
                    yield new ToolCallResult(...array_map($this->convertToolCall(...), $toolCalls));
                    $toolCalls = [];
                }

                break;
            }

            $data = $chunk->getArrayData();

            if ($this->streamContainsToolCall($data)) {
                $toolCalls = $this->collectStreamToolCalls($toolCalls, $data);
            }

            if (!isset($data['choices'][0]['delta']['content'])) {
                continue;
            }

            $content = $data['choices'][0]['delta']['content'];

            if (is_array($content)) {
                $content = $this->normalizeContentArray($content);
            }

            if (!is_string($content) || '' === $content) {
                continue;
            }

            yield $content;
        }
    }

    /**
     * @param array<string, mixed> $chunkData
     */
    private function streamContainsToolCall(array $chunkData): bool
    {
        return isset($chunkData['choices'][0]['delta']['tool_calls']);
    }

    /**
     * @param array<int, array<string, mixed>> $existing
     * @param array<string, mixed>              $chunkData
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectStreamToolCalls(array $existing, array $chunkData): array
    {
        foreach ($chunkData['choices'][0]['delta']['tool_calls'] as $index => $toolCall) {
            if (isset($toolCall['id'])) {
                $existing[$index] = [
                    'id' => $toolCall['id'],
                    'function' => $toolCall['function'] ?? [],
                ];

                continue;
            }

            if (!isset($existing[$index]['function']['arguments'], $toolCall['function']['arguments'])) {
                continue;
            }

            $existing[$index]['function']['arguments'] .= $toolCall['function']['arguments'];
        }

        return $existing;
    }

    /**
     * @param array<string, mixed> $choice
     */
    private function convertChoice(array $choice): ToolCallResult|TextResult
    {
        if (isset($choice['message']['tool_calls']) && is_array($choice['message']['tool_calls']) && [] !== $choice['message']['tool_calls']) {
            return new ToolCallResult(...array_map($this->convertToolCall(...), $choice['message']['tool_calls']));
        }

        $content = $choice['message']['content'] ?? '';

        if (is_array($content)) {
            $content = $this->normalizeContentArray($content);
        }

        if (!is_string($content) || '' === trim($content)) {
            throw new RuntimeException('GitHub Copilot returned an empty response.');
        }

        return new TextResult(trim($content));
    }

    /**
     * @param array<int, array{type: string, text?: string}> $content
     */
    private function normalizeContentArray(array $content): string
    {
        $parts = [];
        foreach ($content as $chunk) {
            if (isset($chunk['text']) && is_string($chunk['text'])) {
                $parts[] = $chunk['text'];
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * @param array<string, mixed> $toolCall
     */
    private function convertToolCall(array $toolCall): ToolCall
    {
        $arguments = [];
        $rawArguments = $toolCall['function']['arguments'] ?? null;

        if (is_string($rawArguments) && '' !== $rawArguments) {
            try {
                $arguments = json_decode($rawArguments, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new RuntimeException('Failed to decode GitHub Copilot tool call arguments.', previous: $e);
            }
        }

        return new ToolCall(
            $toolCall['id'] ?? '',
            $toolCall['function']['name'] ?? '',
            is_array($arguments) ? $arguments : [],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function attachMetadata(ResultInterface $result, array $data): ResultInterface
    {
        if (isset($data['usage']) && is_array($data['usage'])) {
            $tokenUsage = $this->createTokenUsage($data['usage']);
            if (null !== $tokenUsage) {
                $result->getMetadata()->add('token_usage', $tokenUsage);
            }
        }

        if (isset($data['id']) && is_string($data['id'])) {
            $result->getMetadata()->add('response_id', $data['id']);
        }

        if (isset($data['model']) && is_string($data['model'])) {
            $result->getMetadata()->add('model', $data['model']);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $usage
     */
    private function createTokenUsage(array $usage): ?TokenUsage
    {
        $prompt = $this->toIntOrNull($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? $usage['promptTokens'] ?? null);
        $completion = $this->toIntOrNull($usage['completion_tokens'] ?? $usage['output_tokens'] ?? $usage['completionTokens'] ?? null);
        $thinking = $this->toIntOrNull($usage['reasoning_tokens'] ?? $usage['thinking_tokens'] ?? null);
        $tool = $this->toIntOrNull($usage['tool_tokens'] ?? $usage['tool_calls_tokens'] ?? null);
        $cached = $this->toIntOrNull($usage['cached_tokens'] ?? $usage['cache_tokens'] ?? null);
        $remaining = $this->toIntOrNull($usage['remaining_tokens'] ?? null);
        $remainingMinute = $this->toIntOrNull($usage['remaining_tokens_minute'] ?? null);
        $remainingMonth = $this->toIntOrNull($usage['remaining_tokens_month'] ?? null);
        $total = $this->toIntOrNull($usage['total_tokens'] ?? $usage['totalTokens'] ?? null);

        $values = [$prompt, $completion, $thinking, $tool, $cached, $remaining, $remainingMinute, $remainingMonth, $total];
        $hasValue = array_filter($values, static fn (?int $value) => null !== $value) !== [];

        if (!$hasValue) {
            return null;
        }

        return new TokenUsage(
            promptTokens: $prompt,
            completionTokens: $completion,
            thinkingTokens: $thinking,
            toolTokens: $tool,
            cachedTokens: $cached,
            remainingTokens: $remaining,
            remainingTokensMinute: $remainingMinute,
            remainingTokensMonth: $remainingMonth,
            totalTokens: $total,
        );
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function extractErrorMessage(HttpResponse $response): ?string
    {
        try {
            $data = json_decode($response->getContent(false), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return $data['error']['message'] ?? $data['message'] ?? null;
    }
}