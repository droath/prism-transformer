# Prism Transformer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/droath/prism-transformer.svg?style=flat-square)](https://packagist.org/packages/droath/prism-transformer)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/droath/prism-transformer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/droath/prism-transformer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/droath/prism-transformer/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/droath/prism-transformer/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
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
- **📄 Content Sources**: Transform text content directly or fetch from URLs
- **⚡ Intelligent Caching**: Multi-layer caching for content fetching and
  transformation results
- **🛠️ Custom Transformers**: Create reusable transformation classes with
  dependency injection
- **🎯 Laravel Model Output**: Direct transformation to Laravel Eloquent models
  with automatic schema generation
- **🔧 Laravel Integration**: Service provider, facades, configuration, and
  Artisan commands
- **✅ Validation Support**: Built-in Laravel validation integration
- **🏗️ Service-Oriented Architecture**: Clean separation of concerns with
  dedicated services
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

If you discover any security-related issues, please contact us directly
instead of using the issue tracker.

## Credits

- [Travis Tomka](https://github.com/droath)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more
information.
