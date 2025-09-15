# Prism Transformer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/droath/prism-transformer.svg?style=flat-square)](https://packagist.org/packages/droath/prism-transformer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/droath/prism-transformer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/droath/prism-transformer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/droath/prism-transformer/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/droath/prism-transformer/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/droath/prism-transformer.svg?style=flat-square)](https://packagist.org/packages/droath/prism-transformer)

A powerful Laravel package for AI-powered content transformation using
multiple LLM providers.

Transform text content and web URLs into structured data
using providers such as OpenAI, Anthropic, Groq, and more, with intelligent
caching and robust error handling.

## Features

- **🤖 Multi-Provider Support**: OpenAI, Anthropic, Groq, Ollama, Gemini,
  Mistral, DeepSeek, xAI, OpenRouter, VoyageAI, ElevenLabs
- **🚀 Fluent Interface**: Chainable methods for intuitive transformation
  workflows
- **📄 Content Sources**: Transform text content directly or fetch from URLs
- **⚡ Intelligent Caching**: Multi-layer caching for content fetching and
  transformation results
- **🛠️ Custom Transformers**: Create reusable transformation classes with
  dependency injection
- **🔧 Laravel Integration**: Service provider, facades, configuration, and
  Artisan commands
- **✅ Validation Support**: Built-in Laravel validation integration
- **🎯 Error Handling**: Comprehensive exception system with transformation
  context
- **📊 Performance Optimized**: Efficient algorithms and configurable timeout
  settings

## Installation

Install the package via Composer:

```bash
composer require droath/prism-transformer
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="prism-transformer-config"
```

## Configuration

### Environment Variables

Set up your AI provider API keys in your `.env` file:

```env
# Default provider
PRISM_TRANSFORMER_DEFAULT_PROVIDER=openai

# API Keys
OPENAI_API_KEY=your-openai-api-key
ANTHROPIC_API_KEY=your-anthropic-api-key
GROQ_API_KEY=your-groq-api-key

# Caching (optional)
PRISM_TRANSFORMER_CACHE_ENABLED=true
PRISM_TRANSFORMER_CACHE_TTL_CONTENT=1800
PRISM_TRANSFORMER_CACHE_TTL_TRANSFORMATIONS=3600
```

### Provider Configuration

The package supports multiple AI providers with customizable models and
settings:

```php
// config/prism-transformer.php
return [
    'default_provider' => Provider::OPENAI,

    'providers' => [
        'openai' => [
            'default_model' => 'gpt-4o-mini',
            'max_tokens' => 4096,
            'temperature' => 0.7,
        ],
        'anthropic' => [
            'default_model' => 'claude-3-5-haiku-20241022',
            'max_tokens' => 4096,
            'temperature' => 0.7,
        ],
        // ... more providers
    ],
];
```

## Quick Start

### Basic Text Transformation

```php
use Droath\PrismTransformer\PrismTransformer;

// Create a simple transformer using a closure
$transformer = new PrismTransformer();

$result = $transformer
    ->text('Hello world!')
    ->using(function($content) {
        return TransformerResult::successful("Transformed: $content");
    })
    ->transform();

echo $result->getContent(); // "Transformed: Hello world!"
```

### URL Content Transformation

```php
$result = $transformer
    ->url('https://example.com/article')
    ->using($customTransformer)
    ->transform();
```

### Using the Facade

```php
use Droath\PrismTransformer\Facades\PrismTransformer;

$result = PrismTransformer::text('Content to transform')
    ->using($transformer)
    ->transform();
```

## Custom Transformers

Create powerful, reusable transformers by extending `BaseTransformer`:

### Simple Content Summarizer

```php
<?php

use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\ValueObjects\TransformerResult;

class ArticleSummarizer extends BaseTransformer
{
    public function prompt(): string
    {
        return 'Summarize the following article in 2-3 sentences, focusing on key points:';
    }
}
```

Usage:

```php
$summarizer = app(ArticleSummarizer::class);

$result = PrismTransformer::url('https://techcrunch.com/article')
    ->using($summarizer)
    ->transform();

if ($result->isSuccessful()) {
    echo $result->getContent();
}
```

### Data Extraction with Structured Output

```php
<?php

use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Droath\PrismTransformer\Abstract\BaseTransformer;

class ContactExtractor extends BaseTransformer
{
    public function prompt(): string
    {
        return 'Extract contact information from the provided text.';
    }

    public function outputFormat(ObjectSchema|Model $format = null): ?ObjectSchema
    {
        return ObjectSchema::create()
            ->properties([
                'name' => StringSchema::create()->description('Full name'),
                'email' => StringSchema::create()->description('Email address'),
                'phone' => StringSchema::create()->description('Phone number'),
                'company' => StringSchema::create()->description('Company name'),
            ])
            ->required(['name']);
    }
}
```

Usage:

```php
$extractor = app(ContactExtractor::class);

$businessCard = "John Smith - Senior Developer at TechCorp Inc.
                Email: john.smith@techcorp.com | Phone: (555) 123-4567";

$result = PrismTransformer::text($businessCard)
    ->using($extractor)
    ->transform();

$contactData = $result->getContent();
// ['name' => 'John Smith', 'email' => 'john.smith@techcorp.com', ...]
```

## Real-World Examples

### 1. Blog Post SEO Optimizer

```php
class SEOOptimizer extends BaseTransformer
{
    private array $keywords;

    public function __construct(CacheManager $cache, ConfigurationService $config, array $keywords = [])
    {
        parent::__construct($cache, $config);
        $this->keywords = $keywords;
    }

    public function prompt(): string
    {
        $keywordsList = implode(', ', $this->keywords);
        return "Optimize the following blog post for SEO. Target keywords: {$keywordsList}.
                Ensure natural keyword integration while maintaining readability.";
    }

    private function calculateSEOScore(string $content): float
    {
        // Simple SEO scoring logic
        $score = 0;
        foreach ($this->keywords as $keyword) {
            $density = substr_count(strtolower($content), strtolower($keyword));
            $score += min($density * 10, 100); // Cap at 100 per keyword
        }
        return min($score / count($this->keywords), 100);
    }
}

// Usage
$optimizer = new SEOOptimizer(
    app('cache'),
    app(ConfigurationService::class),
    ['Laravel', 'PHP', 'web development']
);

$result = PrismTransformer::url('https://blog.example.com/laravel-tips')
    ->using($optimizer)
    ->transform();

if ($result->isSuccessful()) {
    $optimizedPost = $result->getContent();
    $seoData = $result->getMetadata()->toArray();

    echo "SEO Score: {$seoData['optimization_score']}%\n";
    echo "Word Count: {$seoData['word_count']}\n";
}
```

### 2. Multi-Language Content Translator

```php
class ContentTranslator extends BaseTransformer
{
    public function __construct(
        CacheManager $cache,
        ConfigurationService $config,
        private string $targetLanguage = 'Spanish',
        private bool $maintainFormatting = true
    ) {
        parent::__construct($cache, $config);
    }

    public function prompt(): string
    {
        $formatInstruction = $this->maintainFormatting ?
            'Maintain all HTML tags, markdown formatting, and structure.' : '';

        return "Translate the following content to {$this->targetLanguage}.
                Ensure cultural appropriateness and natural language flow. {$formatInstruction}";
    }

    protected function performTransformation(string $content): TransformerResult
    {
        // Use a specialized model for translation
        $prism = Prism::text()
            ->using($this->provider()->value, 'gpt-4o') // Use more capable model
            ->withPrompt($this->prompt())
            ->withTemperature(0.3) // Lower temperature for more consistent translations
            ->withMaxTokens(4000);

        try {
            $translatedContent = $prism->generate($content);

            $metadata = TransformerMetadata::create([
                'source_language' => $this->detectLanguage($content),
                'target_language' => $this->targetLanguage,
                'character_count' => strlen($translatedContent),
                'maintains_formatting' => $this->maintainFormatting,
            ]);

            return TransformerResult::successful($translatedContent, $metadata);
        } catch (\Exception $e) {
            return TransformerResult::failed("Translation failed: {$e->getMessage()}");
        }
    }

    private function detectLanguage(string $content): string
    {
        // Simple language detection - in practice, use a proper language detection library
        return 'English'; // Simplified for example
    }
}

// Usage with caching benefits
$translator = new ContentTranslator(
    app('cache'),
    app(ConfigurationService::class),
    'French',
    true
);

// This will use cached result if the same URL was translated before
$result = PrismTransformer::url('https://company.com/about')
    ->using($translator)
    ->transform();
```

### 3. Customer Feedback Analyzer

```php
class FeedbackAnalyzer extends BaseTransformer
{
    public function prompt(): string
    {
        return 'Analyze customer feedback and provide structured insights including sentiment, key issues, and recommendations.';
    }

    public function outputFormat(ObjectSchema|Model $format = null): ?ObjectSchema
    {
        return ObjectSchema::create()
            ->properties([
                'sentiment' => StringSchema::create()
                    ->enum(['positive', 'negative', 'neutral'])
                    ->description('Overall sentiment'),
                'sentiment_score' => NumberSchema::create()
                    ->minimum(-1)
                    ->maximum(1)
                    ->description('Sentiment score from -1 to 1'),
                'key_issues' => ArraySchema::create()
                    ->items(StringSchema::create())
                    ->description('List of main issues mentioned'),
                'priority_level' => StringSchema::create()
                    ->enum(['low', 'medium', 'high', 'urgent'])
                    ->description('Priority for addressing feedback'),
                'recommended_actions' => ArraySchema::create()
                    ->items(StringSchema::create())
                    ->description('Suggested actions to address feedback'),
                'categories' => ArraySchema::create()
                    ->items(StringSchema::create())
                    ->description('Categorization of feedback topics'),
            ])
            ->required(['sentiment', 'sentiment_score', 'priority_level']);
    }

    protected function performTransformation(string $content): TransformerResult
    {
        $prism = Prism::structured()
            ->using($this->provider()->value, $this->model())
            ->withSchema($this->outputFormat())
            ->withPrompt($this->prompt())
            ->withTemperature(0.4);

        try {
            $analysis = $prism->generate($content);

            $metadata = TransformerMetadata::create([
                'analyzed_at' => now()->toISOString(),
                'text_length' => strlen($content),
                'confidence_level' => $this->calculateConfidence($analysis),
            ]);

            return TransformerResult::successful($analysis, $metadata);
        } catch (\Exception $e) {
            return TransformerResult::failed("Feedback analysis failed: {$e->getMessage()}");
        }
    }

    private function calculateConfidence(array $analysis): float
    {
        // Calculate confidence based on analysis completeness
        $requiredFields = ['sentiment', 'sentiment_score', 'priority_level'];
        $completedFields = array_intersect_key($analysis, array_flip($requiredFields));

        return (count($completedFields) / count($requiredFields)) * 100;
    }
}

// Batch process multiple feedback entries
$analyzer = app(FeedbackAnalyzer::class);
$feedbackEntries = [
    "Product quality has declined significantly. Very disappointed!",
    "Great customer service, quick response time. Very satisfied.",
    "Website is hard to navigate, checkout process is confusing.",
];

foreach ($feedbackEntries as $feedback) {
    $result = PrismTransformer::text($feedback)
        ->using($analyzer)
        ->transform();

    if ($result->isSuccessful()) {
        $analysis = $result->getContent();
        echo "Sentiment: {$analysis['sentiment']} (Score: {$analysis['sentiment_score']})\n";
        echo "Priority: {$analysis['priority_level']}\n";
        echo "Actions: " . implode(', ', $analysis['recommended_actions']) . "\n\n";
    }
}
```

## Caching System

The package includes a sophisticated two-layer caching system:

### Content Fetch Caching

```php
// Configure in config/prism-transformer.php
'cache' => [
    'ttl' => [
        'content_fetch' => 1800, // 30 minutes for fetched content
        'transformer_data' => 3600, // 1 hour for transformation results
    ],
];
```

### Cache Usage Examples

```php
// First call - fetches and caches URL content and transformation
$result1 = PrismTransformer::url('https://news.site.com/article')
    ->using($summarizer)
    ->transform();

// Second call - uses cached content and transformation
$result2 = PrismTransformer::url('https://news.site.com/article')
    ->using($summarizer)
    ->transform(); // Much faster!

// Different transformer, same URL - uses cached content, new transformation
$result3 = PrismTransformer::url('https://news.site.com/article')
    ->using($translator)
    ->transform(); // Faster content fetch, new transformation
```

## Content Fetchers

Customize how content is retrieved from URLs:

```php
use Droath\PrismTransformer\ContentFetchers\BasicHttpFetcher;

$customFetcher = new BasicHttpFetcher([
    'timeout' => 60,
    'user_agent' => 'MyApp/1.0',
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
    ],
]);

$result = PrismTransformer::url('https://api.example.com/data', $customFetcher)
    ->using($dataProcessor)
    ->transform();
```

## Error Handling

The package provides comprehensive error handling:

```php
$result = PrismTransformer::text('problematic content')
    ->using($complexTransformer)
    ->transform();

if ($result->isSuccessful()) {
    $content = $result->getContent();
    $metadata = $result->getMetadata();
} else {
    $error = $result->getError();
    Log::error("Transformation failed: {$error}");

    // Handle different types of failures
    if (str_contains($error, 'rate limit')) {
        // Implement retry logic
    } elseif (str_contains($error, 'timeout')) {
        // Handle timeout
    }
}
```

### Exception Types

- `TransformerException`: General transformation errors
- `FetchException`: Content fetching failures
- `ValidationException`: Input validation errors
- `InvalidInputException`: Invalid input data
- `UnsupportedTypeException`: Unsupported content types

## Testing

Run the test suite:

```bash
composer test

# Run specific test files
vendor/bin/pest tests/Feature/TransformationPipelineIntegrationTest.php

# Run with coverage
vendor/bin/pest --coverage
```

### Testing Your Transformers

```php
use Droath\PrismTransformer\PrismTransformer;
use Tests\TestCase;

class ArticleSummarizerTest extends TestCase
{
    public function test_summarizes_article_successfully()
    {
        $summarizer = app(ArticleSummarizer::class);

        $content = "Long article content here...";

        $result = (new PrismTransformer())
            ->text($content)
            ->using($summarizer)
            ->transform();

        expect($result->isSuccessful())->toBeTrue();
        expect($result->getContent())->toBeString();
        expect(strlen($result->getContent()))->toBeLessThan(strlen($content));
    }
}
```

## Performance Optimization

### Best Practices

1. **Use Caching**: Enable caching for repeated transformations
2. **Choose Appropriate Models**: Use smaller models for simple tasks
3. **Batch Processing**: Process multiple items efficiently
4. **Set Reasonable Timeouts**: Configure appropriate timeout values

```php
// Efficient batch processing
$transformer = app(ContentClassifier::class);
$items = collect($largeDataSet)
    ->chunk(10) // Process in chunks
    ->map(function ($chunk) use ($transformer) {
        return $chunk->map(function ($item) use ($transformer) {
            return PrismTransformer::text($item)
                ->using($transformer)
                ->transform();
        });
    })
    ->flatten();
```

## Artisan Commands

The package includes helpful Artisan commands:

```bash
# Clear transformation cache
php artisan prism-transformer:cache:clear

# Test provider connectivity
php artisan prism-transformer:test-providers

# Generate a custom transformer class
php artisan make:prism-transformer BlogSummarizer
```

## Advanced Configuration

### Multiple Provider Setup

```php
// Use different providers for different tasks
class MultiProviderTransformer extends BaseTransformer
{
    public function provider(): Provider
    {
        // Use Anthropic for analysis, OpenAI for generation
        return $this->isAnalysisTask ? Provider::ANTHROPIC : Provider::OPENAI;
    }
}
```

### Custom Cache Stores

```php
// config/prism-transformer.php
'cache' => [
    'store' => 'redis', // Use Redis for better performance
    'prefix' => 'prism_v2',
],
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details on how to contribute to
this package.

## Security

If you discover any security-related issues, please email security@example.com
instead of using the issue tracker.

## Credits

- [Travis Tomka](https://github.com/droath)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more
information.
