# Prism Transformer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/droath/prism-transformer.svg?style=flat-square)](https://packagist.org/packages/droath/prism-transformer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/droath/prism-transformer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/droath/prism-transformer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/droath/prism-transformer/phpstan.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/droath/prism-transformer/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/droath/prism-transformer.svg?style=flat-square)](https://packagist.org/packages/droath/prism-transformer)

A powerful Laravel package for AI-powered content transformation using
multiple LLM providers with direct Laravel Eloquent model integration.

Transform text content and web URLs into structured data or Laravel models
using providers such as OpenAI, Anthropic, Groq, and more, with intelligent
caching, automatic schema generation, and robust error handling.

## Features

- **🤖 Multi-Provider Support**: OpenAI, Anthropic, Groq, Ollama, Gemini,
  Mistral, DeepSeek, xAI, OpenRouter, VoyageAI, ElevenLabs
- **🚀 Fluent Interface**: Chainable methods for intuitive transformation
  workflows
- **📄 Content Sources**: Transform text, URLs, images, and documents
- **🖼️ Media Input Support**: Native support for image and document
  transformation with AI vision
- **⚡ Intelligent Caching**: Two-layer caching system for content fetching and
  transformation results
- **🔄 Async Processing**: Queue transformations for background processing with
  Laravel queues
- **🛠️ Custom Transformers**: Create reusable transformation classes with
  dependency injection
- **🎯 Laravel Model Output**: Direct transformation to Laravel Eloquent models
  with automatic schema generation
- **🔧 Laravel Integration**: Service provider, facades, configuration, and
  Artisan commands
- **✅ Validation Support**: Built-in Laravel validation integration
- **🛡️ Security Features**: Blocked domains protection for content fetching
- **🏗️ Service-Oriented Architecture**: Clean separation of concerns with
  dedicated services
- **🎯 Error Handling**: Comprehensive Throwable-based exception system with
  transformation context
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

### Requirements

- PHP 8.3+
- Laravel 11.0+ or 12.0+

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
PRISM_TRANSFORMER_CACHE_CONTENT_ENABLED=true
PRISM_TRANSFORMER_CACHE_RESULTS_ENABLED=true
PRISM_TRANSFORMER_CACHE_TTL_CONTENT=1800
PRISM_TRANSFORMER_CACHE_TTL_RESULTS=3600
PRISM_TRANSFORMER_CACHE_STORE=redis

# Async Queue (optional)
PRISM_TRANSFORMER_ASYNC_QUEUE=default
PRISM_TRANSFORMER_QUEUE_CONNECTION=redis
PRISM_TRANSFORMER_TIMEOUT=60
PRISM_TRANSFORMER_TRIES=3
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

### Laravel Model Transformation

Transform content directly into Laravel Eloquent models:

```php
use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Abstract\BaseTransformer;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = ['name', 'email', 'phone'];
}

class ContactExtractor extends BaseTransformer
{
    public function prompt(): string
    {
        return 'Extract contact information from the text as JSON.';
    }

    protected function outputFormat(): Model
    {
        return new Contact();
    }
}

$result = (new PrismTransformer())
    ->text('John Smith, email: john@example.com, phone: (555) 123-4567')
    ->using(ContactExtractor::class)
    ->transform();

// Convert directly to Laravel model
$contact = $result->toModel(Contact::class);
$contact->save(); // Save to database

echo $contact->name;  // "John Smith"
echo $contact->email; // "john@example.com"
```

### URL Content Transformation

```php
$result = $transformer
    ->url('https://example.com/article')
    ->using($customTransformer)
    ->transform();
```

### Media Input Transformation

Transform images and documents with AI vision and document analysis:

```php
use Droath\PrismTransformer\PrismTransformer;

// Image transformation
$result = (new PrismTransformer())
    ->image('/path/to/image.jpg')
    ->using($imageAnalyzer)
    ->transform();

// Document transformation
$result = (new PrismTransformer())
    ->document('/path/to/document.pdf')
    ->using($documentExtractor)
    ->transform();

// You can also pass metadata
$result = (new PrismTransformer())
    ->image('/path/to/photo.png', ['title' => 'Product Photo'])
    ->using($productAnalyzer)
    ->transform();
```

The media input methods automatically handle:

- **File reading and encoding**: Converts files to base64 for AI processing
- **Media type detection**: Automatically determines image vs document types
- **Proper serialization**: Works with both sync and async transformations

### Async Transformations

Queue transformations for background processing using Laravel's queue system:

```php
use Droath\PrismTransformer\PrismTransformer;

// Async text transformation
$pendingDispatch = (new PrismTransformer())
    ->text('Long content to process...')
    ->async()
    ->using($summarizer)
    ->transform();

// Async URL transformation
$pendingDispatch = (new PrismTransformer())
    ->url('https://example.com/article')
    ->async()
    ->using($contentAnalyzer)
    ->transform();

// Async image transformation
$pendingDispatch = (new PrismTransformer())
    ->image('/path/to/image.jpg')
    ->async()
    ->using($imageDescriber)
    ->transform();

// The transform() method returns a PendingDispatch instance
// The job will be processed by your queue worker
```

**Async with Context:**

```php
$pendingDispatch = (new PrismTransformer())
    ->setContext(['user_id' => auth()->id(), 'tenant_id' => 123])
    ->text('Content to transform')
    ->async()
    ->using($transformer)
    ->transform();
```

**Async with Closures:**

Closures are automatically serialized for queue processing and properly handle
Media objects:

```php
// Works with both string and Media input
$pendingDispatch = (new PrismTransformer())
    ->image('/path/to/image.jpg')
    ->async()
    ->using(function ($content) {
        // $content will be a Media object (Image instance)
        // Properly handle both types
        $data = is_string($content) ? $content : $content->base64();

        return TransformerResult::successful("Processed: {$data}");
    })
    ->transform();
```

**Queue Configuration:**

```php
// config/prism-transformer.php
'transformation' => [
    'async_queue' => env('PRISM_TRANSFORMER_ASYNC_QUEUE', 'default'),
    'queue_connection' => env('PRISM_TRANSFORMER_QUEUE_CONNECTION'),
    'timeout' => env('PRISM_TRANSFORMER_TIMEOUT', 60),
    'tries' => env('PRISM_TRANSFORMER_TRIES', 3),
],
```

### Using the Facade

```php
use Droath\PrismTransformer\Facades\PrismTransformer;

$result = PrismTransformer::text('Content to transform')
    ->using($transformer)
    ->transform();

// Async with facade
$pendingDispatch = PrismTransformer::text('Content to transform')
    ->async()
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

### Data Extraction with Laravel Model Output

Transform content directly into Laravel Eloquent models with automatic schema
generation:

```php
<?php

use Illuminate\Database\Eloquent\Model;
use Droath\PrismTransformer\Abstract\BaseTransformer;

class User extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'company'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}

class ContactExtractor extends BaseTransformer
{
    public function prompt(): string
    {
        return 'Extract contact information from the provided text and structure it as user data.';
    }

    protected function outputFormat(): Model
    {
        return new User();
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

// Convert result directly to Laravel model
$user = $result->toModel(User::class);

echo $user->name;     // "John Smith"
echo $user->email;    // "john.smith@techcorp.com"
echo $user->phone;    // "(555) 123-4567"
echo $user->company;  // "TechCorp Inc."
```

### Traditional Structured Output (Still Supported)

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

    protected function outputFormat(): ?ObjectSchema
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

## Real-World Examples

### 1. Blog Post SEO Optimizer

```php
use Droath\PrismTransformer\Abstract\BaseTransformer;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\Services\ModelSchemaService;
use Droath\PrismTransformer\Facades\PrismTransformer;
use Illuminate\Cache\CacheManager;

class SEOOptimizer extends BaseTransformer
{
    private array $keywords;

    public function __construct(
        CacheManager $cache,
        ConfigurationService $configuration,
        ModelSchemaService $modelSchemaService,
        array $keywords = []
    ) {
        parent::__construct($cache, $configuration, $modelSchemaService);
        $this->keywords = $keywords;
    }

    public function prompt(): string
    {
        $keywordsList = implode(', ', $this->keywords);
        return "Optimize the following blog post for SEO. Target keywords: {$keywordsList}.
                Ensure natural keyword integration while maintaining readability.
                Improve meta descriptions, headings, and overall content structure for better search engine ranking.";
    }

    protected function provider(): Provider
    {
        return Provider::OPENAI; // Use OpenAI for content optimization
    }

    protected function model(): string
    {
        return 'gpt-4o-mini'; // Good balance of quality and cost for content work
    }

    protected function temperature(): ?float
    {
        return 0.3; // Lower temperature for more consistent, focused output
    }
}

// Usage - Optimizing a blog post from a URL
$result = PrismTransformer::url('https://blog.example.com/laravel-tips')
    ->using(new SEOOptimizer(
        app(CacheManager::class),
        app(ConfigurationService::class),
        app(ModelSchemaService::class),
        ['Laravel', 'PHP', 'web development', 'framework']
    ))
    ->transform();

if ($result->isSuccessful()) {
    $optimizedPost = $result->getContent();
    $metadata = $result->getMetadata();

    echo "Optimization completed successfully!\n";
    echo "Provider: {$metadata?->provider->value}\n";
    echo "Model: {$metadata?->model}\n";
    echo "Content length: " . strlen($optimizedPost) . " characters\n";

    // Save the optimized content
    file_put_contents('optimized-post.md', $optimizedPost);
} else {
    echo "SEO optimization failed:\n";
    foreach ($result->getErrors() as $error) {
        echo "- {$error}\n";
    }
}

// Alternative: Direct text optimization
$blogContent = "Your existing blog post content here...";

$result = PrismTransformer::text($blogContent)
    ->using(SEOOptimizer::class) // Can also use class name for auto-resolution
    ->transform();
```

### 2. Multi-Language Content Translator

```php
class ContentTranslator extends BaseTransformer
{
    public function __construct(
        CacheManager $cache,
        ConfigurationService $configuration,
        ModelSchemaService $modelSchemaService,
        private string $targetLanguage = 'Spanish',
        private bool $maintainFormatting = true
    ) {
        parent::__construct($cache, $configuration, $modelSchemaService);
    }

    public function prompt(): string
    {
        $formatInstruction = $this->maintainFormatting ?
            'Maintain all HTML tags, markdown formatting, and structure.' : '';

        return "Translate the following content to {$this->targetLanguage}.
                Ensure cultural appropriateness and natural language flow. {$formatInstruction}";
    }

    protected function model(): string
    {
        // Use a more capable model for translation tasks
        return 'gpt-4o';
    }

    protected function temperature(): ?float
    {
        // Lower temperature for more consistent translations
        return 0.3;
    }

    private function detectLanguage(string $content): string
    {
        // Simple language detection - in practice, use a proper language detection library
        return 'English'; // Simplified for example
    }
}

// Usage with caching benefits - dependency injection handles constructor parameters
$result = PrismTransformer::url('https://company.com/about')
    ->using(ContentTranslator::class)
    ->transform();

// Or create instance manually for custom parameters
$translator = new ContentTranslator(
    app(CacheManager::class),
    app(ConfigurationService::class),
    app(ModelSchemaService::class),
    'French',
    true
);

$result = PrismTransformer::text('Hello, how are you today?')
    ->using($translator)
    ->transform();

if ($result->isSuccessful()) {
    echo $result->getContent(); // "Bonjour, comment allez-vous aujourd'hui ?"
    $metadata = $result->getMetadata();
    echo "Model used: " . $metadata->model;
    echo "Provider: " . $metadata->provider->value;
}
```

### 3. Customer Feedback Analyzer with Laravel Models

Transform customer feedback directly into Laravel models for seamless database
integration:

```php
<?php

use Illuminate\Database\Eloquent\Model;
use Droath\PrismTransformer\Abstract\BaseTransformer;

class FeedbackAnalysis extends Model
{
    protected $fillable = [
        'sentiment',
        'sentiment_score',
        'key_issues',
        'priority_level',
        'recommended_actions',
        'categories',
        'confidence_level',
    ];

    protected $casts = [
        'sentiment_score' => 'float',
        'key_issues' => 'array',
        'recommended_actions' => 'array',
        'categories' => 'array',
        'confidence_level' => 'float',
    ];
}

class FeedbackAnalyzer extends BaseTransformer
{
    public function prompt(): string
    {
        return 'Analyze customer feedback and provide structured insights including sentiment, key issues, and recommendations. Format the response as JSON.';
    }

    protected function outputFormat(): Model
    {
        return new FeedbackAnalysis();
    }
}

// Usage - Process feedback and save to database
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
        // Convert to model with validation
        $validationRules = [
            'sentiment' => 'required|in:positive,negative,neutral',
            'sentiment_score' => 'required|numeric|between:-1,1',
            'priority_level' => 'required|in:low,medium,high,urgent',
        ];

        $analysis = $result->toModel(FeedbackAnalysis::class, $validationRules);

        // Save to database
        $analysis->save();

        echo "Sentiment: {$analysis->sentiment} (Score: {$analysis->sentiment_score})\n";
        echo "Priority: {$analysis->priority_level}\n";
        echo "Saved analysis with ID: {$analysis->id}\n\n";
    }
}
```

## Laravel Model Integration

The package provides first-class Laravel Eloquent model integration, allowing
you to transform AI responses directly into model instances with automatic
schema generation and validation.

### Model Output Format

Use the `outputFormat()` method in your transformers to specify a Laravel model
as the expected output:

```php
<?php

use Illuminate\Database\Eloquent\Model;
use Droath\PrismTransformer\Abstract\BaseTransformer;

class Product extends Model
{
    protected $fillable = ['name', 'price', 'category', 'description', 'in_stock'];

    protected $casts = [
        'price' => 'decimal:2',
        'in_stock' => 'boolean',
    ];
}

class ProductExtractor extends BaseTransformer
{
    public function prompt(): string
    {
        return 'Extract product information from the text and format it as structured data.';
    }

    protected function outputFormat(): Model
    {
        return new Product();
    }
}
```

### Schema Generation

The `ModelSchemaService` automatically converts Laravel model definitions into
Prism schemas:

- **Fillable attributes** become schema properties
- **Model casts** determine the appropriate schema types
- **Type mapping** handles Laravel cast types to Prism schema types

Supported Laravel cast types:

- `string` → `StringSchema`
- `integer`, `int` → `NumberSchema`
- `float`, `double`, `decimal` → `NumberSchema`
- `boolean`, `bool` → `BooleanSchema`
- `array`, `json` → `ArraySchema`
- `collection` → `ArraySchema`
- `date`, `datetime`, `timestamp` → `StringSchema` (with ISO 8601 format)

### Model Hydration

Transform results can be converted directly to model instances using the
`toModel()` method:

```php
$result = PrismTransformer::text($productDescription)
    ->using(ProductExtractor::class)
    ->transform();

// Convert to model with optional validation
$product = $result->toModel(Product::class, [
    'name' => 'required|string|max:255',
    'price' => 'required|numeric|min:0',
    'category' => 'required|string',
    'in_stock' => 'required|boolean',
]);

// Save to database
$product->save();
```

### Service-Oriented Architecture

The model integration uses a clean service-oriented approach:

- **`ModelSchemaService`**: Handles model-to-schema conversion and data-to-model
  hydration
- **Separation of concerns**: Model logic is isolated from transformation logic
- **Dependency injection**: Services are injected via Laravel's container
- **Testability**: Services can be easily mocked and tested independently

## Caching System

The package includes a sophisticated two-layer caching system to optimize
performance and reduce API costs:

### Two-Layer Cache Architecture

1. **Content Fetch Cache**: Caches raw content from URLs and files
2. **Transformer Results Cache**: Caches AI transformation results

This separation allows you to:

- Reuse fetched content across different transformers
- Cache expensive AI transformations independently
- Control cache TTL separately for each layer

### Configuration

```php
// config/prism-transformer.php
'cache' => [
    'store' => env('PRISM_TRANSFORMER_CACHE_STORE', 'default'),
    'prefix' => env('PRISM_TRANSFORMER_CACHE_PREFIX', 'prism_transformer'),

    'content_fetch' => [
        'enabled' => env('PRISM_TRANSFORMER_CACHE_CONTENT_ENABLED', false),
        'ttl' => env('PRISM_TRANSFORMER_CACHE_TTL_CONTENT', 1800), // 30 minutes
    ],

    'transformer_results' => [
        'enabled' => env('PRISM_TRANSFORMER_CACHE_RESULTS_ENABLED', false),
        'ttl' => env('PRISM_TRANSFORMER_CACHE_TTL_RESULTS', 3600), // 1 hour
    ],
],
```

### Cache Keys

Cache keys are intelligently generated based on:

- **Content fetch cache**: URL + fetch options
- **Transformer results cache**: Content + context + transformer configuration

This ensures accurate cache hits while avoiding false positives.

### Cache Usage Examples

```php
// First call - fetches content and performs transformation, caches both
$result1 = PrismTransformer::url('https://news.site.com/article')
    ->using($summarizer)
    ->transform();

// Second call - uses both cached content AND cached transformation
$result2 = PrismTransformer::url('https://news.site.com/article')
    ->using($summarizer)
    ->transform(); // Much faster!

// Different transformer, same URL - uses cached content, new transformation
$result3 = PrismTransformer::url('https://news.site.com/article')
    ->using($translator)
    ->transform(); // Skips HTTP fetch, performs new AI transformation

// Different context invalidates transformer cache
$result4 = PrismTransformer::url('https://news.site.com/article')
    ->setContext(['language' => 'es'])
    ->using($summarizer)
    ->transform(); // Uses cached content, new transformation with context
```

### Environment Configuration

```env
# Enable/disable caching
PRISM_TRANSFORMER_CACHE_CONTENT_ENABLED=true
PRISM_TRANSFORMER_CACHE_RESULTS_ENABLED=true

# Cache TTL in seconds
PRISM_TRANSFORMER_CACHE_TTL_CONTENT=1800
PRISM_TRANSFORMER_CACHE_TTL_RESULTS=3600

# Use Redis for better performance (optional)
PRISM_TRANSFORMER_CACHE_STORE=redis
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

### Blocked Domains

Protect your application from fetching content from untrusted or malicious
domains:

```php
// config/prism-transformer.php
'content_fetcher' => [
    'validation' => [
        'blocked_domains' => [
            'malicious-site.com',
            '*.spam-domain.net',  // Wildcard pattern
            'internal.local',
        ],
        'allowed_schemes' => ['http', 'https'],
    ],
],
```

The blocked domains feature supports:

- **Exact domain matching**: `example.com` blocks only that specific domain
- **Wildcard patterns**: `*.example.com` blocks all subdomains
- **Automatic validation**: Blocked URLs will throw a `FetchException`

```php
// This will throw FetchException
$result = PrismTransformer::url('https://malicious-site.com/content')
    ->using($transformer)
    ->transform(); // Throws: FetchException
```

## Error Handling

The package provides comprehensive error handling with support for both
exceptions and PHP errors using `\Throwable`:

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
- `FetchException`: Content fetching failures (including blocked domains)
- `ValidationException`: Input validation errors
- `InvalidInputException`: Invalid input data
- `RateLimitExceededException`: Rate limit exceeded errors

### Throwable Support

The package uses `\Throwable` instead of `\Exception` in error handling to catch
both user-defined exceptions and PHP internal errors (like `TypeError`). This is
particularly important for:

- **Queue job failure handlers**: Jobs can fail due to type errors in PHP 8.3+
- **Event dispatching**: `TransformationFailed` event accepts any throwable
- **Robust error handling**: Catches all error types, not just exceptions

```php
use Droath\PrismTransformer\Events\TransformationFailed;

// Listen for transformation failures
Event::listen(TransformationFailed::class, function (TransformationFailed $event) {
    // $event->exception is \Throwable (catches both Exception and Error)
    Log::error('Transformation failed', [
        'error' => $event->exception->getMessage(),
        'type' => get_class($event->exception),
        'content' => $event->content,
        'context' => $event->context,
    ]);
});
```

## Events

The package dispatches events throughout the transformation lifecycle, allowing
you to hook into the process for monitoring, logging, and custom handling:

### Available Events

#### TransformationStarted

Dispatched when a transformation begins (sync or async):

```php
use Droath\PrismTransformer\Events\TransformationStarted;

Event::listen(TransformationStarted::class, function (TransformationStarted $event) {
    Log::info('Transformation started', [
        'content_preview' => is_string($event->content)
            ? substr($event->content, 0, 50)
            : get_class($event->content),
        'context' => $event->context,
    ]);
});
```

**Event Properties:**

- `content` (string|Media|null): The content being transformed
- `context` (array): Additional context data

#### TransformationCompleted

Dispatched when a transformation completes successfully:

```php
use Droath\PrismTransformer\Events\TransformationCompleted;

Event::listen(TransformationCompleted::class, function (TransformationCompleted $event) {
    Log::info('Transformation completed', [
        'success' => $event->result->isSuccessful(),
        'provider' => $event->result->getMetadata()?->provider->value,
        'model' => $event->result->getMetadata()?->model,
        'context' => $event->context,
    ]);

    // Track metrics, send notifications, etc.
    Metrics::increment('transformations.success');
});
```

**Event Properties:**

- `result` (TransformerResult): The transformation result
- `context` (array): Additional context data

#### TransformationFailed

Dispatched when a transformation fails:

```php
use Droath\PrismTransformer\Events\TransformationFailed;

Event::listen(TransformationFailed::class, function (TransformationFailed $event) {
    Log::error('Transformation failed', [
        'error' => $event->exception->getMessage(),
        'error_type' => get_class($event->exception),
        'content' => $event->content,
        'context' => $event->context,
    ]);

    // Send alert, track metrics, etc.
    if ($event->context['user_id'] ?? null) {
        Notification::send(
            User::find($event->context['user_id']),
            new TransformationFailedNotification($event->exception)
        );
    }
});
```

**Event Properties:**

- `exception` (\Throwable): The exception that caused the failure
- `content` (string|Media|null): The content being transformed
- `context` (array): Additional context data

### Event Usage Examples

**Monitor All Transformations:**

```php
// In EventServiceProvider
protected $listen = [
    TransformationStarted::class => [
        LogTransformationStart::class,
        TrackTransformationMetrics::class,
    ],
    TransformationCompleted::class => [
        LogTransformationComplete::class,
        UpdateUserCredits::class,
    ],
    TransformationFailed::class => [
        LogTransformationFailure::class,
        SendAdminAlert::class,
    ],
];
```

**Context-Aware Event Handling:**

```php
// Pass context for better tracking
$result = PrismTransformer::text($content)
    ->setContext([
        'user_id' => auth()->id(),
        'request_id' => request()->id(),
        'feature' => 'article_summarization',
    ])
    ->using($summarizer)
    ->transform();

// Event listener can use context
Event::listen(TransformationCompleted::class, function ($event) {
    if ($event->context['feature'] === 'article_summarization') {
        // Feature-specific handling
    }
});
```

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
use Prism\Prism\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
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

    public function test_transforms_to_model_with_validation()
    {
        // Mock the AI response
        $fakeResponse = StructuredResponseFake::make()
            ->withStructured([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => 30,
                'is_active' => true
            ])
            ->withUsage(new Usage(10, 20));

        Prism::fake([$fakeResponse]);

        $transformer = app(UserExtractor::class);

        $result = (new PrismTransformer())
            ->text('Extract user: John Doe, email john@example.com, age 30, active')
            ->using($transformer)
            ->transform();

        expect($result->isSuccessful())->toBeTrue();

        // Test model conversion with validation
        $user = $result->toModel(User::class, [
            'name' => 'required|string',
            'email' => 'required|email',
            'age' => 'required|integer|min:18'
        ]);

        expect($user)->toBeInstanceOf(User::class);
        expect($user->name)->toBe('John Doe');
        expect($user->email)->toBe('john@example.com');
        expect($user->age)->toBe(30);
        expect($user->is_active)->toBeTrue();
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

If you discover any security-related issues, please contact us directly
instead of using the issue tracker.

## Credits

- [Travis Tomka](https://github.com/droath)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more
information.
