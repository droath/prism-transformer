<?php

declare(strict_types=1);

use Droath\PrismTransformer\Testing\TransformerTestCase;
use Droath\PrismTransformer\Testing\TransformerExpectations;
use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Droath\PrismTransformer\Enums\Provider;

/**
 * Example test class demonstrating the testing utilities.
 *
 * This test file shows various ways to test transformers using the
 * provided testing helpers and expectations. It serves as both
 * documentation and validation of the testing infrastructure.
 */
class ExampleTransformerUsageTest extends TransformerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register custom expectations for Pest
        TransformerExpectations::register();
    }

    public function test_basic_text_transformation()
    {
        $content = $this->getSampleContent('article');
        $expectedResult = 'This is a summarized version of the article.';

        $result = $this->transformText($content)
            ->using($this->createMockTransformer($expectedResult))
            ->transform();

        $this->assertTransformationSucceeded($result);
        $this->assertTransformationEquals($expectedResult, $result);
    }

    public function test_url_transformation_with_http_mock()
    {
        $url = 'https://example.com/article';
        $htmlContent = '<html><body><h1>Test Article</h1><p>Article content here.</p></body></html>';
        $expectedSummary = 'Test Article: Article content here.';

        // Mock the HTTP response
        $this->mockHttpResponse($url, $htmlContent);

        $result = $this->transformUrl($url, $htmlContent)
            ->using($this->createMockTransformer($expectedSummary))
            ->transform();

        $this->assertTransformationSucceeded($result);
        $this->assertTransformationContains('Test Article', $result);
    }

    public function test_transformation_failure_handling()
    {
        $content = 'Content that will fail';
        $expectedErrors = ['Transformation failed', 'Invalid input'];

        $result = $this->transformText($content)
            ->using($this->createFailingTransformer($expectedErrors))
            ->transform();

        $this->assertTransformationFailed($result);
        $this->assertTransformationError('Transformation failed', $result);
    }

    public function test_caching_behavior()
    {
        $content = 'Cacheable content';
        $transformedContent = 'Cached transformation result';

        // Enable caching
        $this->enableCache();

        // First transformation
        $result1 = $this->transformText($content)
            ->using($this->createMockTransformer($transformedContent))
            ->transform();

        $this->assertTransformationSucceeded($result1);

        // Manually verify cache is enabled and working
        // Note: Mock transformers don't use the actual cache system
        // This test demonstrates the testing utilities work
        $this->assertTrue(config('prism-transformer.cache.enabled'), 'Cache should be enabled');

        // Test that cache is properly configured
        $this->assertConfigEquals('prism-transformer.cache.enabled', true);
    }

    public function test_cache_disabled_behavior()
    {
        $content = 'Non-cacheable content';

        // Disable caching
        $this->disableCache();

        $result = $this->transformText($content)
            ->using($this->createMockTransformer('Result'))
            ->transform();

        $this->assertTransformationSucceeded($result);
        $this->assertCacheEmpty('Cache should remain empty when disabled');
    }

    public function test_different_content_types()
    {
        $testCases = [
            'article' => 'article summary',
            'email' => 'email analysis',
            'social' => 'social media analysis',
            'code' => 'code review',
            'json' => 'data analysis',
        ];

        foreach ($testCases as $contentType => $expectedPrefix) {
            $content = $this->getSampleContent($contentType);
            $expected = "{$expectedPrefix}: processed";

            $result = $this->transformText($content)
                ->using($this->createMockTransformer($expected))
                ->transform();

            $this->assertTransformationSucceeded($result, "Failed for content type: {$contentType}");
            $this->assertTransformationContains($expectedPrefix, $result);
        }
    }

    public function test_metadata_handling()
    {
        $content = 'Content with metadata';
        $metadata = TransformerMetadata::make('gpt-4o-mini', Provider::OPENAI, 'TestTransformer');

        $result = $this->transformText($content)
            ->using($this->createMockTransformer('Result', $metadata))
            ->transform();

        $this->assertTransformationSucceeded($result);
        $this->assertTransformationMetadata('provider', Provider::OPENAI, $result);
    }

    public function test_performance_assertion()
    {
        $content = 'Performance test content';

        $this->assertTransformationPerformance(1.0, function () use ($content) {
            return $this->transformText($content)
                ->using($this->createMockTransformer('Fast result'))
                ->transform();
        });
    }

    public function test_content_length_comparisons()
    {
        $originalContent = str_repeat('This is a long article with lots of content. ', 50);
        $summarizedContent = 'Short summary of the long article.';

        $result = $this->transformText($originalContent)
            ->using($this->createMockTransformer($summarizedContent))
            ->transform();

        $this->assertTransformationShorter($originalContent, $result);
    }

    public function test_pattern_matching()
    {
        $content = 'Extract email addresses from this text';
        $emailResult = 'Found emails: user@example.com, admin@test.org';

        $result = $this->transformText($content)
            ->using($this->createMockTransformer($emailResult))
            ->transform();

        $this->assertTransformationMatches('/[\w\.-]+@[\w\.-]+\.\w+/', $result);
    }

    public function test_http_error_handling()
    {
        // Test that we can create a failing content fetcher
        $failingFetcher = $this->createMockContentFetcher('', true);

        // Verify the fetcher throws when called directly
        $this->expectException(\Droath\PrismTransformer\Exceptions\FetchException::class);
        $failingFetcher->fetch('https://example.com');
    }

    public function test_custom_content_fetcher()
    {
        $url = 'https://api.example.com/data';
        $customContent = 'Custom fetched content';

        $customFetcher = $this->createMockContentFetcher($customContent);

        $result = (new PrismTransformer())
            ->url($url, $customFetcher)
            ->using($this->createMockTransformer('Processed: '.$customContent))
            ->transform();

        $this->assertTransformationContains('Custom fetched content', $result);
    }

    public function test_provider_configuration()
    {
        // Test with different provider
        $this->setDefaultProvider('anthropic');
        $this->setProviderModel('anthropic', 'claude-3-sonnet');

        $this->assertConfigEquals('prism-transformer.default_provider', 'anthropic');
        $this->assertConfigEquals('prism-transformer.providers.anthropic.default_model', 'claude-3-sonnet');
    }
}

/*
 * Note: The following are example Pest-style tests showing how to use
 * the TransformerExpectations. To use these in a real test file, you would:
 *
 * 1. Create a proper test case that extends TransformerTestCase
 * 2. Use the testing helpers directly within the test methods
 * 3. Register the expectations in your Pest.php file
 *
 * Example usage in a real Pest test file:
 *
 * uses(TransformerTestCase::class);
 *
 * beforeEach(function () {
 *     TransformerExpectations::register();
 * });
 *
 * test('transformation succeeds with fluent expectations', function () {
 *     $result = $this->transformText('test content')
 *         ->using($this->createMockTransformer('transformed content'))
 *         ->transform();
 *
 *     expect($result)
 *         ->toBeSuccessful()
 *         ->toHaveContent('transformed content')
 *         ->toHaveNoErrors();
 * });
 */
