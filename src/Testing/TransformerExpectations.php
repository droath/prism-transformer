<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Testing;

use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Pest\Expectation;
use PHPUnit\Framework\Assert;

/**
 * Custom Pest expectations for TransformerResult testing.
 *
 * This class provides fluent expectations specifically designed for testing
 * TransformerResult objects in Pest tests. It extends Pest's expectation
 * system with domain-specific assertions for transformation results.
 *
 * @example Basic usage:
 * ```php
 * expect($transformerResult)
 *     ->toBeSuccessful()
 *     ->toContain('expected text')
 *     ->toHaveMetadata('provider', Provider::OPENAI);
 * ```
 * @example Testing failures:
 * ```php
 * expect($transformerResult)
 *     ->toBeFailed()
 *     ->toHaveError('API rate limit exceeded');
 * ```
 *
 * @mixin Expectation
 */
class TransformerExpectations
{
    /**
     * Register custom expectations with Pest.
     *
     * This method should be called in your Pest.php file to register
     * all custom expectations for TransformerResult objects.
     */
    public static function register(): void
    {
        expect()->extend('toBeSuccessful', function () {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isSuccessful(),
                'Expected transformation to be successful. Errors: '.implode(', ', $result->getErrors())
            );

            return $this;
        });

        expect()->extend('toBeFailed', function () {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isFailed(),
                'Expected transformation to be failed'
            );

            return $this;
        });

        expect()->extend('toBePending', function () {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isPending(),
                'Expected transformation to be pending'
            );

            return $this;
        });

        expect()->extend('toBeInProgress', function () {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isInProgress(),
                'Expected transformation to be in progress'
            );

            return $this;
        });

        expect()->extend('toHaveContent', function (string $expectedContent) {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isSuccessful(),
                'Cannot check content of failed transformation. Errors: '.implode(', ', $result->getErrors())
            );

            Assert::assertEquals(
                $expectedContent,
                $result->getContent(),
                'Transformation content does not match expected value'
            );

            return $this;
        });

        expect()->extend('toContain', function (string $needle) {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isSuccessful(),
                'Cannot check content of failed transformation. Errors: '.implode(', ', $result->getErrors())
            );

            Assert::assertStringContainsString(
                $needle,
                $result->getContent(),
                "Transformation content does not contain: $needle"
            );

            return $this;
        });

        expect()->extend('toMatch', function (string $pattern) {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isSuccessful(),
                'Cannot check content of failed transformation. Errors: '.implode(', ', $result->getErrors())
            );

            Assert::assertMatchesRegularExpression(
                $pattern,
                $result->getContent(),
                "Transformation content does not match pattern: $pattern"
            );

            return $this;
        });

        expect()->extend('toHaveError', function (string $expectedError) {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isFailed(),
                'Expected transformation to have failed'
            );

            Assert::assertContains(
                $expectedError,
                $result->getErrors(),
                "Expected error '$expectedError' not found in: ".implode(', ', $result->getErrors())
            );

            return $this;
        });

        expect()->extend('toHaveErrors', function (?int $expectedCount = null) {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            $errors = $result->getErrors();

            if ($expectedCount === null) {
                Assert::assertNotEmpty(
                    $errors,
                    'Expected transformation to have errors'
                );
            } else {
                Assert::assertCount(
                    $expectedCount,
                    $errors,
                    "Expected $expectedCount errors, got ".count($errors)
                );
            }

            return $this;
        });

        expect()->extend('toHaveNoErrors', function () {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertEmpty(
                $result->getErrors(),
                'Expected no errors, got: '.implode(', ', $result->getErrors())
            );

            return $this;
        });

        expect()->extend('toHaveMetadata', function (string $key, $expectedValue = null) {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            $metadata = $result->getMetadata();
            Assert::assertNotNull($metadata, 'Expected transformation to have metadata');

            $metadataArray = $metadata->toArray();
            Assert::assertArrayHasKey($key, $metadataArray, "Expected metadata to have key: $key");

            if ($expectedValue !== null) {
                Assert::assertEquals(
                    $expectedValue,
                    $metadataArray[$key],
                    "Metadata value for '$key' does not match expected value"
                );
            }

            return $this;
        });

        expect()->extend('toHaveStatus', function (string $expectedStatus) {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertEquals(
                $expectedStatus,
                $result->getStatus(),
                "Expected status '$expectedStatus', got '{$result->getStatus()}'"
            );

            return $this;
        });

        expect()->extend('toBeShorterThan', function (string $originalContent) {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isSuccessful(),
                'Cannot check length of failed transformation. Errors: '.implode(', ', $result->getErrors())
            );

            $transformedLength = strlen($result->getContent());
            $originalLength = strlen($originalContent);

            Assert::assertLessThan(
                $originalLength,
                $transformedLength,
                "Expected transformed content ($transformedLength chars) to be shorter than original ($originalLength chars)"
            );

            return $this;
        });

        expect()->extend('toBeLongerThan', function (string $originalContent) {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isSuccessful(),
                'Cannot check length of failed transformation. Errors: '.implode(', ', $result->getErrors())
            );

            $transformedLength = strlen($result->getContent());
            $originalLength = strlen($originalContent);

            Assert::assertGreaterThan(
                $originalLength,
                $transformedLength,
                "Expected transformed content ($transformedLength chars) to be longer than original ($originalLength chars)"
            );

            return $this;
        });

        expect()->extend('toHaveContentLength', function (int $expectedLength, int $tolerance = 0) {
            /** @var TransformerResult $result */
            $result = $this->value;

            Assert::assertInstanceOf(
                TransformerResult::class,
                $result,
                'Expected value to be a TransformerResult instance'
            );

            Assert::assertTrue(
                $result->isSuccessful(),
                'Cannot check length of failed transformation. Errors: '.implode(', ', $result->getErrors())
            );

            $actualLength = strlen($result->getContent());

            if ($tolerance === 0) {
                Assert::assertEquals(
                    $expectedLength,
                    $actualLength,
                    "Expected content length $expectedLength, got $actualLength"
                );
            } else {
                $minLength = $expectedLength - $tolerance;
                $maxLength = $expectedLength + $tolerance;

                Assert::assertGreaterThanOrEqual(
                    $minLength,
                    $actualLength,
                    "Content length $actualLength is below minimum expected $minLength (tolerance: ±$tolerance)"
                );

                Assert::assertLessThanOrEqual(
                    $maxLength,
                    $actualLength,
                    "Content length $actualLength is above maximum expected $maxLength (tolerance: ±$tolerance)"
                );
            }

            return $this;
        });
    }
}
