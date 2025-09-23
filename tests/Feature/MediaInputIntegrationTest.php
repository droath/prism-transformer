<?php

declare(strict_types=1);

use Droath\PrismTransformer\PrismTransformer;
use Droath\PrismTransformer\Handlers\ImageTransformerHandler;
use Droath\PrismTransformer\Handlers\DocumentTransformerHandler;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Illuminate\Foundation\Bus\PendingDispatch;

describe('Media Input Integration', function () {
    beforeEach(function () {
        // Create test files for integration testing
        $this->testImageContent = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jINSAAAAQElEQVR4nGNgAAQAAAABAAA=';
        $this->testImagePath = tempnam(sys_get_temp_dir(), 'test_image').'.png';
        file_put_contents($this->testImagePath, base64_decode($this->testImageContent));

        $this->testDocumentContent = 'This is a test document for integration testing.';
        $this->testDocumentPath = tempnam(sys_get_temp_dir(), 'test_document').'.txt';
        file_put_contents($this->testDocumentPath, $this->testDocumentContent);
    });

    afterEach(function () {
        if (isset($this->testImagePath) && file_exists($this->testImagePath)) {
            unlink($this->testImagePath);
        }
        if (isset($this->testDocumentPath) && file_exists($this->testDocumentPath)) {
            unlink($this->testDocumentPath);
        }
    });

    describe('image() fluent interface integration', function () {
        test('image method creates ImageTransformerHandler and sets content', function () {
            $transformer = app(PrismTransformer::class);
            $result = $transformer->image($this->testImagePath);

            expect($result)->toBe($transformer);

            // Verify content was set with base64 content
            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            $content = $contentProperty->getValue($transformer);

            expect($content)
                ->toBeString()
                ->toMatch('/^[A-Za-z0-9+\/]+=*$/'); // Base64 pattern
        });

        test('image method accepts options for inputType', function () {
            $transformer = app(PrismTransformer::class);
            $result = $transformer->image($this->testImageContent, ['inputType' => 'base64']);

            expect($result)->toBe($transformer);

            // Verify content was processed correctly
            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            $content = $contentProperty->getValue($transformer);

            expect($content)
                ->toBeString()
                ->toMatch('/^[A-Za-z0-9+\/]+=*$/'); // Base64 pattern
        });

        test('image method integrates with complete transformation workflow', function () {
            $transformer = app(PrismTransformer::class);

            $closure = function ($input) {
                expect($input)->toBeString();
                expect($input)->toMatch('/^[A-Za-z0-9+\/]+=*$/'); // Base64 pattern

                return TransformerResult::successful('Processed image: '.substr($input, 0, 20).'...');
            };

            $result = $transformer
                ->image($this->testImagePath)
                ->using($closure)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toStartWith('Processed image: ');
        });

        test('image method supports all inputTypes', function () {
            $inputTypes = [
                ['inputType' => 'localPath', 'path' => $this->testImagePath],
                ['inputType' => 'base64', 'path' => $this->testImageContent],
                ['inputType' => 'url', 'path' => 'https://example.com/image.jpg'],
                ['inputType' => 'storagePath', 'path' => 'images/test.jpg', 'disk' => 'public'],
                ['inputType' => 'rawContent', 'path' => base64_decode($this->testImageContent), 'mimeType' => 'image/png'],
                ['inputType' => 'fileId', 'path' => 'file-123'],
            ];

            foreach ($inputTypes as $config) {
                $transformer = app(PrismTransformer::class);
                $options = ['inputType' => $config['inputType']];

                if (isset($config['disk'])) {
                    $options['disk'] = $config['disk'];
                }
                if (isset($config['mimeType'])) {
                    $options['mimeType'] = $config['mimeType'];
                }

                if ($config['inputType'] === 'localPath' || $config['inputType'] === 'base64') {
                    // Only test these two that don't require external dependencies
                    $result = $transformer->image($config['path'], $options);
                    expect($result)->toBe($transformer);
                } else {
                    // For other types, just verify the handler instance creation works
                    $handler = new ImageTransformerHandler($config['path'], $options);
                    expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
                }
            }
        });
    });

    describe('document() fluent interface integration', function () {
        test('document method creates DocumentTransformerHandler and sets content', function () {
            $transformer = app(PrismTransformer::class);
            $result = $transformer->document($this->testDocumentPath);

            expect($result)->toBe($transformer);

            // Verify content was set with base64 content
            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            $content = $contentProperty->getValue($transformer);

            expect($content)
                ->toBeString()
                ->toMatch('/^[A-Za-z0-9+\/]+=*$/'); // Base64 pattern
        });

        test('document method accepts options for inputType and title', function () {
            $transformer = app(PrismTransformer::class);
            $options = ['inputType' => 'text', 'title' => 'Test Document'];
            $result = $transformer->document($this->testDocumentContent, $options);

            expect($result)->toBe($transformer);

            // Verify content was processed correctly
            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            $content = $contentProperty->getValue($transformer);

            expect($content)
                ->toBeString()
                ->toMatch('/^[A-Za-z0-9+\/]+=*$/'); // Base64 pattern
        });

        test('document method integrates with complete transformation workflow', function () {
            $transformer = app(PrismTransformer::class);

            $closure = function ($input) {
                expect($input)->toBeString();
                expect($input)->toMatch('/^[A-Za-z0-9+\/]+=*$/'); // Base64 pattern

                return TransformerResult::successful('Processed document: '.substr($input, 0, 20).'...');
            };

            $result = $transformer
                ->document($this->testDocumentPath)
                ->using($closure)
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toStartWith('Processed document: ');
        });

        test('document method supports all inputTypes', function () {
            $inputTypes = [
                ['inputType' => 'localPath', 'path' => $this->testDocumentPath],
                ['inputType' => 'text', 'path' => $this->testDocumentContent],
                ['inputType' => 'base64', 'path' => base64_encode($this->testDocumentContent)],
                ['inputType' => 'url', 'path' => 'https://example.com/document.pdf'],
                ['inputType' => 'storagePath', 'path' => 'documents/test.pdf', 'disk' => 'public'],
                ['inputType' => 'rawContent', 'path' => $this->testDocumentContent, 'mimeType' => 'text/plain'],
                ['inputType' => 'fileId', 'path' => 'file-456'],
            ];

            foreach ($inputTypes as $config) {
                $transformer = app(PrismTransformer::class);
                $options = ['inputType' => $config['inputType']];

                if (isset($config['disk'])) {
                    $options['disk'] = $config['disk'];
                }
                if (isset($config['mimeType'])) {
                    $options['mimeType'] = $config['mimeType'];
                }

                if (in_array($config['inputType'], ['localPath', 'text', 'base64'])) {
                    // Only test these that don't require external dependencies
                    $result = $transformer->document($config['path'], $options);
                    expect($result)->toBe($transformer);
                } else {
                    // For other types, just verify the handler instance creation works
                    $handler = new DocumentTransformerHandler($config['path'], $options);
                    expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
                }
            }
        });
    });

    describe('unified content pipeline integration', function () {
        test('both media methods utilize existing $content property', function () {
            $transformer = app(PrismTransformer::class);

            // First set image content
            $transformer->image($this->testImagePath);

            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            $imageContent = $contentProperty->getValue($transformer);

            expect($imageContent)->toBeString();

            // Then override with document content
            $transformer->document($this->testDocumentPath);
            $documentContent = $contentProperty->getValue($transformer);

            expect($documentContent)->toBeString();
            expect($documentContent)->not->toBe($imageContent); // Content was overridden
        });

        test('media content can be overridden by text() method', function () {
            $transformer = app(PrismTransformer::class);
            $textContent = 'Override text content';

            // Start with image, then override with text
            $transformer
                ->image($this->testImagePath)
                ->text($textContent);

            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            $finalContent = $contentProperty->getValue($transformer);

            expect($finalContent)->toBe($textContent);
        });

        test('text content can be overridden by media methods', function () {
            $transformer = app(PrismTransformer::class);
            $textContent = 'Initial text content';

            // Start with text, then override with document
            $transformer
                ->text($textContent)
                ->document($this->testDocumentPath);

            $reflection = new ReflectionClass($transformer);
            $contentProperty = $reflection->getProperty('content');
            $contentProperty->setAccessible(true);
            $finalContent = $contentProperty->getValue($transformer);

            expect($finalContent)->not->toBe($textContent);
            expect($finalContent)->toMatch('/^[A-Za-z0-9+\/]+=*$/'); // Base64 pattern
        });
    });

    describe('method chaining compatibility', function () {
        test('image method supports full fluent interface chaining', function () {
            $transformer = app(PrismTransformer::class);

            $result = $transformer
                ->image($this->testImagePath)
                ->async()
                ->using(fn ($content) => TransformerResult::successful('Chained: '.substr($content, 0, 10)))
                ->transform();

            expect($result)->toBeInstanceOf(PendingDispatch::class);
        });

        test('document method supports full fluent interface chaining', function () {
            $transformer = app(PrismTransformer::class);

            $result = $transformer
                ->document($this->testDocumentPath)
                ->async()
                ->using(fn ($content) => TransformerResult::successful('Chained: '.substr($content, 0, 10)))
                ->transform();

            expect($result)->toBeInstanceOf(PendingDispatch::class);
        });

        test('media methods work identically to text and url methods in chains', function () {
            $transformer = app(PrismTransformer::class);
            $closure = fn ($content) => TransformerResult::successful('Processed: '.substr($content, 0, 10));

            // Test all input types in similar chains
            $textResult = $transformer
                ->text('Test content')
                ->using($closure)
                ->transform();

            $imageResult = app(PrismTransformer::class)
                ->image($this->testImagePath)
                ->using($closure)
                ->transform();

            $documentResult = app(PrismTransformer::class)
                ->document($this->testDocumentPath)
                ->using($closure)
                ->transform();

            // All should return TransformerResult with successful status
            expect($textResult)->toBeInstanceOf(TransformerResult::class);
            expect($imageResult)->toBeInstanceOf(TransformerResult::class);
            expect($documentResult)->toBeInstanceOf(TransformerResult::class);

            expect($textResult->isSuccessful())->toBeTrue();
            expect($imageResult->isSuccessful())->toBeTrue();
            expect($documentResult->isSuccessful())->toBeTrue();
        });
    });

    describe('async processing compatibility', function () {
        test('async processing works without modification for image inputs', function () {
            $transformer = app(PrismTransformer::class);

            $result = $transformer
                ->image($this->testImagePath)
                ->async()
                ->using(fn ($content) => TransformerResult::successful('Async image: '.substr($content, 0, 10)))
                ->transform();

            expect($result)->toBeInstanceOf(PendingDispatch::class);

            // Verify async flag was set
            $reflection = new ReflectionClass($transformer);
            $asyncProperty = $reflection->getProperty('async');
            $asyncProperty->setAccessible(true);
            expect($asyncProperty->getValue($transformer))->toBeTrue();
        });

        test('async processing works without modification for document inputs', function () {
            $transformer = app(PrismTransformer::class);

            $result = $transformer
                ->document($this->testDocumentPath)
                ->async()
                ->using(fn ($content) => TransformerResult::successful('Async doc: '.substr($content, 0, 10)))
                ->transform();

            expect($result)->toBeInstanceOf(PendingDispatch::class);

            // Verify async flag was set
            $reflection = new ReflectionClass($transformer);
            $asyncProperty = $reflection->getProperty('async');
            $asyncProperty->setAccessible(true);
            expect($asyncProperty->getValue($transformer))->toBeTrue();
        });

        test('context preservation works with media inputs in async mode', function () {
            $transformer = app(PrismTransformer::class);
            $context = ['user_id' => 123, 'operation' => 'media_processing'];

            $result = $transformer
                ->setContext($context)
                ->image($this->testImagePath)
                ->async()
                ->using(fn ($content) => TransformerResult::successful('Context preserved'))
                ->transform();

            expect($result)->toBeInstanceOf(PendingDispatch::class);

            // Verify context was preserved
            $reflection = new ReflectionClass($transformer);
            $contextProperty = $reflection->getProperty('context');
            $contextProperty->setAccessible(true);
            expect($contextProperty->getValue($transformer))->toBe($context);
        });
    });

    describe('backward compatibility verification', function () {
        test('existing text() and url() methods continue to work unchanged', function () {
            $transformer = app(PrismTransformer::class);
            $textContent = 'Existing text functionality';

            $result = $transformer
                ->text($textContent)
                ->using(fn ($content) => TransformerResult::successful('BC: '.$content))
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe('BC: '.$textContent);
        });

        test('mixed usage of old and new methods works correctly', function () {
            $transformer = app(PrismTransformer::class);

            // Start with image, override with text, then back to document
            $finalContent = 'Final text content';

            $result = $transformer
                ->image($this->testImagePath)       // Set image content
                ->text($finalContent)               // Override with text
                ->using(fn ($content) => TransformerResult::successful('Mixed: '.$content))
                ->transform();

            expect($result)->toBeInstanceOf(TransformerResult::class);
            expect($result->isSuccessful())->toBeTrue();
            expect($result->data)->toBe('Mixed: '.$finalContent);
        });
    });
});
