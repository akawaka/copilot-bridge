# 🤖 Akawaka Copilot Bridge

A Symfony bridge that integrates GitHub Copilot AI models into your applications.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-blue)](https://www.php.net/)

## ✨ Features

- 🚀 **Easy Integration** - Drop-in Symfony bridge for GitHub Copilot
- 🎯 **Multiple Models** - Support for Claude, GPT, Gemini, and more
- 🔐 **Secure Authentication** - Built-in OAuth device flow
- 📝 **Console Commands** - Ready-to-use CLI commands

## 📦 Installation

Install the bridge using Composer:

```bash
composer require akawaka/copilot-bridge
```

That's it! The bridge automatically registers its services and commands.

## 🏗️ Available Models

The bridge provides access to multiple AI model families:

### 🧠 Claude Models
```php
use Akawaka\Bridge\Copilot\Copilot;

// Latest Claude models
Copilot::CLAUDE_35_SONNET    // claude-3.5-sonnet
Copilot::CLAUDE_37_SONNET    // claude-3.7-sonnet
Copilot::CLAUDE_OPUS_4       // claude-opus-4
```

### 🤖 GPT Models
```php
// GPT family models
Copilot::GPT_5              // gpt-5
Copilot::GPT_5_MINI         // gpt-5-mini
Copilot::GPT_4O             // gpt-4o
Copilot::O3                 // o3
Copilot::O3_MINI            // o3-mini
```

### 🔮 Gemini Models
```php
// Google Gemini models
Copilot::GEMINI_20_FLASH    // gemini-2.0-flash-001
Copilot::GEMINI_25_PRO      // gemini-2.5-pro
```

## 🚀 Quick Start

### 1. Authentication

First, authenticate with GitHub Copilot:

```bash
php bin/console copilot:auth
```

Follow the prompts to complete OAuth authentication.

### 2. Using Models in Code

```php
use Akawaka\Bridge\Copilot\Copilot;
use Akawaka\Bridge\Copilot\PlatformFactory;
use Symfony\Component\HttpClient\HttpClient;

// Create a model instance
$model = new Copilot(Copilot::GPT_5_MINI, [
    'temperature' => 0.7,
    'max_tokens' => 1000
]);

// Create the platform
$httpClient = HttpClient::create();
$platform = PlatformFactory::create($httpClient);

// Send a prompt
$messages = new MessageBag(Message::ofUser('Hello, how are you?'));
$result = $platform->invoke($model, $messages);

echo $result->asText();
```

### 3. Using with Dependency Injection

```yaml
# config/services.yaml
services:
    App\Service\MyAIService:
        arguments:
            $copilotAuthService: '@Akawaka\Bridge\Copilot\Auth\CopilotAuthService'
            $platformFactory: '@Akawaka\Bridge\Copilot\PlatformFactory'
```

```php
// src/Service/MyAIService.php
use Akawaka\Bridge\Copilot\Auth\CopilotAuthService;
use Akawaka\Bridge\Copilot\Copilot;
use Akawaka\Bridge\Copilot\PlatformFactory;

class MyAIService
{
    public function __construct(
        private CopilotAuthService $authService,
        private PlatformFactory $platformFactory
    ) {}
    
    public function askQuestion(string $question): string
    {
        $model = new Copilot(Copilot::CLAUDE_35_SONNET);
        $platform = $this->platformFactory->create($this->httpClient);
        
        $messages = new MessageBag(Message::ofUser($question));
        $result = $platform->invoke($model, $messages, [
            'copilot_access_token' => $this->authService->getAccessToken()
        ]);
        
        return $result->asText();
    }
}
```

## ⚙️ Configuration

### Basic Configuration

The bridge works out-of-the-box with default settings. For custom configuration:

```yaml
# config/packages/copilot.yaml
copilot:
    client_id: 'your-github-app-client-id'
    auth_token_expiration: 90  # days
    models:
        custom_model:
            capabilities: ['input_messages', 'output_text']
            options:
                temperature: 0.5
                max_tokens: 2000
```

## 🖥️ Console Commands

The bridge provides three console commands:

### `copilot:auth` - Authentication
```bash
# Basic authentication
php bin/console copilot:auth

# With custom timeout
php bin/console copilot:auth --timeout=600

# Check existing token first
php bin/console copilot:auth --check-existing
```

### `copilot:status` - Check Status
```bash
php bin/console copilot:status
```

### `copilot:logout` - Remove Tokens
```bash
php bin/console copilot:logout
```

## 🔧 Advanced Usage

### Custom Model Options

```php
$model = new Copilot(Copilot::CLAUDE_35_SONNET, [
    'temperature' => 0.8,        // Creativity level (0-1)
    'max_tokens' => 2000,        // Maximum response length
    'top_p' => 0.9,              // Nucleus sampling
    'frequency_penalty' => 0.1,   // Reduce repetition
    'presence_penalty' => 0.1     // Encourage new topics
]);
```

### Streaming Responses

```php
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Message;

$messages = new MessageBag(Message::ofUser('Tell me a story'));
$result = $platform->invoke($model, $messages, [
    'stream' => true,
    'copilot_access_token' => $accessToken
]);

// Handle streaming response
foreach ($result->stream() as $chunk) {
    echo $chunk->getContent();
}
```

### Error Handling

```php
use Akawaka\Bridge\Copilot\Exception\CopilotException;
use Akawaka\Bridge\Copilot\Exception\AuthenticationException;

try {
    $result = $platform->invoke($model, $messages);
    return $result->asText();
} catch (AuthenticationException $e) {
    // Handle authentication errors
    throw new \RuntimeException('Please run: php bin/console copilot:auth');
} catch (CopilotException $e) {
    // Handle other Copilot errors
    throw new \RuntimeException('Copilot error: ' . $e->getMessage());
}
```

## 📋 Model Capabilities

Different models support different capabilities:

| Model Family | Text Input | Image Input | Streaming | Tool Calling |
|--------------|------------|-------------|-----------|--------------|
| Claude       | ✅         | ✅          | ✅        | ✅           |
| GPT          | ✅         | ✅          | ✅        | ✅           |
| Gemini       | ✅         | ✅          | ✅        | ✅           |

## 🧪 Testing

The bridge includes comprehensive tests:

```bash
# Run tests
./vendor/bin/phpunit

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/my-feature`
3. Commit your changes: `git commit -m 'Add feature'`
4. Push to the branch: `git push origin feature/my-feature`
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- 🐛 [Report Issues](https://github.com/akawaka/copilot-bridge/issues)
- 💬 [Discussions](https://github.com/akawaka/copilot-bridge/discussions)

## 🙏 Acknowledgments

- Built on top of [Symfony AI Platform](https://github.com/symfony/ai-platform)

---

Made with ❤️ by [Franck Matsos](https://github.com/fmatsos)