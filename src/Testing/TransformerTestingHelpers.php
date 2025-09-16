<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Testing;

use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Contracts\ContentFetcherInterface;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;

/**
 * Comprehensive testing utilities for PrismTransformer package.
 *
 * This trait provides helper methods for testing transformers, mocking
 * API responses, asserting transformation results, and creating test
 * data. It's designed to make testing AI-powered transformations
 * more straightforward and reliable.
 *
 * @example Basic usage in a test:
 * ```php
 * use Droath\PrismTransformer\Testing\TransformerTestingHelpers;
 *
 * class ArticleSummarizerTest extends TestCase
 * {
 *     use TransformerTestingHelpers;
 *
 *     public function test_summarizes_article_successfully()
 *     {
 *         $this->mockSuccessfulTransformation('Summary of the article');
 *
 *         $result = $this->transformText('Long article content...')
 *             ->using($this->createMockTransformer())
 *             ->transform();
 *
 *         $this->assertTransformationSucceeded($result);
 *         $this->assertTransformationEquals('Summary of the article', $result);
 *     }
 * }
 * ```
 *
 * @api
 */
trait TransformerTestingHelpers
{
    /**
     * Create a new PrismTransformer instance for testing.
     *
     * @return PrismTransformer Fresh transformer instance
     */
    protected function createTransformer(): PrismTransformer
    {
        return new PrismTransformer();
    }

    /**
     * Create a transformer instance with text content pre-loaded.
     *
     * @param string $content The content to set in the transformer
     *
     * @return PrismTransformer Transformer instance with content loaded
     */
    protected function transformText(string $content): PrismTransformer
    {
        return $this->createTransformer()->text($content);
    }

    /**
     * Create a transformer instance with URL content (mocked).
     *
     * @param string $url The URL to mock
     * @param string $content The content to return for the URL
     * @param int $statusCode HTTP status code to return
     *
     * @return PrismTransformer Transformer instance with URL configured
     */
    protected function transformUrl(string $url, string $content = '<html><body>Test content</body></html>', int $statusCode = 200): PrismTransformer
    {
        $this->mockHttpResponse($url, $content, $statusCode);

        return $this->createTransformer()->url($url);
    }

    /**
     * Create a mock transformer that returns a successful result.
     *
     * @param string $transformedContent The content to return
     * @param TransformerMetadata|null $metadata Optional metadata
     *
     * @return \Closure Mock transformer closure
     */
    protected function createMockTransformer(
        string $transformedContent = 'Mocked transformation result',
        ?TransformerMetadata $metadata = null
    ): \Closure {
        return function (string $content) use ($transformedContent, $metadata): TransformerResult {
            return TransformerResult::successful($transformedContent, $metadata);
        };
    }

    /**
     * Create a mock transformer that returns a failed result.
     *
     * @param array<string> $errors Array of error messages
     * @param TransformerMetadata|null $metadata Optional metadata
     *
     * @return \Closure Mock transformer closure that fails
     */
    protected function createFailingTransformer(
        array $errors = ['Mock transformation failed'],
        ?TransformerMetadata $metadata = null
    ): \Closure {
        return function (string $content) use ($errors, $metadata): TransformerResult {
            return TransformerResult::failed($errors, $metadata);
        };
    }

    /**
     * Create a mock content fetcher for testing URL transformations.
     *
     * @param string $content The content to return
     * @param bool $shouldFail Whether the fetcher should fail
     *
     * @return ContentFetcherInterface Mock content fetcher
     */
    protected function createMockContentFetcher(
        string $content = 'Mocked fetched content',
        bool $shouldFail = false
    ): ContentFetcherInterface {
        return new class($content, $shouldFail) implements ContentFetcherInterface
        {
            public function __construct(
                private string $content,
                private bool $shouldFail
            ) {}

            public function fetch(string $url): string
            {
                if ($this->shouldFail) {
                    throw new \Droath\PrismTransformer\Exceptions\FetchException("Mock fetch failed for: $url");
                }

                return $this->content;
            }
        };
    }

    /**
     * Mock HTTP responses for URL-based transformations.
     *
     * @param string $url The URL to mock
     * @param string $body The response body
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional headers
     */
    protected function mockHttpResponse(
        string $url,
        string $body = '<html><body>Test content</body></html>',
        int $status = 200,
        array $headers = []
    ): void {
        Http::fake([
            $url => Http::response($body, $status, array_merge([
                'Content-Type' => 'text/html; charset=utf-8',
            ], $headers)),
        ]);
    }

    /**
     * Mock HTTP responses that will fail.
     *
     * @param string $url The URL to mock
     * @param int $status HTTP status code (4xx or 5xx)
     * @param string $body Error response body
     */
    protected function mockHttpFailure(
        string $url,
        int $status = 404,
        string $body = 'Not Found'
    ): void {
        Http::fake([
            $url => Http::response($body, $status),
        ]);
    }

    /**
     * Create sample test content for various scenarios.
     *
     * @param string $type Type of content ('article', 'email', 'social', 'code', 'json')
     *
     * @return string Sample content of the specified type
     */
    protected function getSampleContent(string $type = 'article'): string
    {
        return match ($type) {
            'article' => 'This is a comprehensive article about artificial intelligence and machine learning. '
                .'It covers various aspects of AI development, including neural networks, deep learning, '
                .'and natural language processing. The article explores practical applications in healthcare, '
                .'finance, and technology sectors.',

            'email' => 'Subject: Meeting Tomorrow\n\n'
                .'Hi John,\n\n'
                .'Just wanted to confirm our meeting tomorrow at 2 PM to discuss the new project requirements. '
                .'Please bring the technical specifications document.\n\n'
                .'Best regards,\n'
                .'Sarah Johnson\n'
                .'Project Manager',

            'social' => 'Just launched our new AI-powered content transformation tool! 🚀 '
                .'It supports multiple LLM providers and has intelligent caching. '
                .'Perfect for developers building content-driven applications. '
                .'#AI #Laravel #PHP #ContentTransformation',

            'code' => '```php
<?php

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn($q) => $q->where("name", "like", "%{$request->search}%"))
            ->paginate(15);

        return view("users.index", compact("users"));
    }
}
```',

            'json' => json_encode([
                'users' => [
                    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
                ],
                'meta' => [
                    'total' => 2,
                    'per_page' => 10,
                    'current_page' => 1,
                ],
            ]),

            default => 'This is generic sample content for testing purposes. '
                .'It contains multiple sentences and various topics to test '
                .'transformation capabilities across different scenarios.',
        };
    }

    // === Assertion Methods ===

    /**
     * Assert that a transformation was successful.
     *
     * @param TransformerResult|null $result The transformation result
     * @param string $message Custom assertion message
     */
    protected function assertTransformationSucceeded(
        ?TransformerResult $result,
        string $message = 'Expected transformation to succeed'
    ): void {
        Assert::assertNotNull($result, 'Transformation result should not be null');
        Assert::assertTrue(
            $result->isSuccessful(),
            $message.'. Errors: '.implode(', ', $result->getErrors())
        );
    }

    /**
     * Assert that a transformation failed.
     *
     * @param TransformerResult|null $result The transformation result
     * @param string $message Custom assertion message
     */
    protected function assertTransformationFailed(
        ?TransformerResult $result,
        string $message = 'Expected transformation to fail'
    ): void {
        Assert::assertNotNull($result, 'Transformation result should not be null');
        Assert::assertTrue($result->isFailed(), $message);
    }

    /**
     * Assert that transformation result equals expected content.
     *
     * @param string $expected Expected transformed content
     * @param TransformerResult|null $result The transformation result
     * @param string $message Custom assertion message
     */
    protected function assertTransformationEquals(
        string $expected,
        ?TransformerResult $result,
        string $message = 'Transformation content does not match expected'
    ): void {
        $this->assertTransformationSucceeded($result);
        Assert::assertEquals($expected, $result->getContent(), $message);
    }

    /**
     * Assert that transformation result contains specific content.
     *
     * @param string $needle Content to search for
     * @param TransformerResult|null $result The transformation result
     * @param string $message Custom assertion message
     */
    protected function assertTransformationContains(
        string $needle,
        ?TransformerResult $result,
        string $message = 'Transformation content does not contain expected text'
    ): void {
        $this->assertTransformationSucceeded($result);
        Assert::assertStringContainsString($needle, $result->getContent(), $message);
    }

    /**
     * Assert that transformation result matches a pattern.
     *
     * @param string $pattern Regular expression pattern
     * @param TransformerResult|null $result The transformation result
     * @param string $message Custom assertion message
     */
    protected function assertTransformationMatches(
        string $pattern,
        ?TransformerResult $result,
        string $message = 'Transformation content does not match pattern'
    ): void {
        $this->assertTransformationSucceeded($result);
        Assert::assertMatchesRegularExpression($pattern, $result->getContent(), $message);
    }

    /**
     * Assert that transformation result is shorter than original content.
     *
     * @param string $originalContent The original content
     * @param TransformerResult|null $result The transformation result
     * @param string $message Custom assertion message
     */
    protected function assertTransformationShorter(
        string $originalContent,
        ?TransformerResult $result,
        string $message = 'Transformed content should be shorter than original'
    ): void {
        $this->assertTransformationSucceeded($result);
        Assert::assertLessThan(
            strlen($originalContent),
            strlen($result->getContent()),
            $message
        );
    }

    /**
     * Assert that transformation has specific metadata.
     *
     * @param string $key Metadata key
     * @param mixed $expectedValue Expected metadata value
     * @param TransformerResult|null $result The transformation result
     * @param string $message Custom assertion message
     */
    protected function assertTransformationMetadata(
        string $key,
        mixed $expectedValue,
        ?TransformerResult $result,
        string $message = 'Transformation metadata does not match'
    ): void {
        $this->assertTransformationSucceeded($result);
        $metadata = $result->getMetadata();
        Assert::assertNotNull($metadata, 'Transformation should have metadata');

        $metadataArray = $metadata->toArray();
        Assert::assertArrayHasKey($key, $metadataArray, "Metadata should have key: $key");
        Assert::assertEquals($expectedValue, $metadataArray[$key], $message);
    }

    /**
     * Assert that transformation errors contain specific message.
     *
     * @param string $expectedError Expected error message
     * @param TransformerResult|null $result The transformation result
     * @param string $message Custom assertion message
     */
    protected function assertTransformationError(
        string $expectedError,
        ?TransformerResult $result,
        string $message = 'Expected error not found in transformation result'
    ): void {
        $this->assertTransformationFailed($result);
        Assert::assertContains($expectedError, $result->getErrors(), $message);
    }

    /**
     * Assert transformation took less than specified time.
     *
     * @param float $maxSeconds Maximum expected duration in seconds
     * @param callable $transformationCallback Callback that performs transformation
     * @param string $message Custom assertion message
     */
    protected function assertTransformationPerformance(
        float $maxSeconds,
        callable $transformationCallback,
        string $message = 'Transformation took longer than expected'
    ): void {
        $startTime = microtime(true);
        $transformationCallback();
        $duration = microtime(true) - $startTime;

        Assert::assertLessThan($maxSeconds, $duration, $message);
    }
}
