<?php
/**
 * API Master Module - API Interface
 * Tüm API provider'ları için ortak interface
 * 
 * @package     APIMaster
 * @subpackage  API
 */

if (!defined('ABSPATH')) {
    exit;
}

interface APIMaster_API_Interface {
    
    public function getName(): string;
    public function getVersion(): string;
    public function getEndpoint(): string;
    public function setApiKey(string $apiKey);
    public function getApiKey(): ?string;
    public function setModel(string $model);
    public function getModel(): string;
    public function setTimeout(int $timeout);
    public function setMaxTokens(int $maxTokens);
    public function setTemperature(float $temperature);
    public function chat(array $messages, array $options = []): array;
    public function complete(string $prompt, array $options = []): array;
    public function generateImage(string $prompt, array $options = []): array;
    public function createEmbedding(string $text, array $options = []): array;
    public function checkHealth(): bool;
    public function getAvailableModels(): array;
    public function countTokens(string $text): int;
    public function getRateLimits(): array;
    public function getCapabilities(): array;
}