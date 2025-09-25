<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Tests\Unit\ValueObjects;

use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Droath\PrismTransformer\Enums\Provider;

describe('TransformerResult getContent() method', function () {
    describe('basic content retrieval', function () {
        test('returns string content when parseJson is false', function () {
            $stringContent = 'This is plain text content';
            $result = TransformerResult::successful($stringContent);

            $content = $result->getContent();

            expect($content)->toBe($stringContent);
            expect($content)->toBeString();
        });

        test('returns string content explicitly when parseJson is false', function () {
            $stringContent = 'This is plain text content';
            $result = TransformerResult::successful($stringContent);

            $content = $result->getContent(parseJson: false);

            expect($content)->toBe($stringContent);
            expect($content)->toBeString();
        });

        test('returns null when transformation failed', function () {
            $result = TransformerResult::failed(['Error occurred']);

            $content = $result->getContent();

            expect($content)->toBeNull();
        });
    });

    describe('JSON object content handling', function () {
        test('returns raw JSON string when parseJson is false', function () {
            $jsonData = ['name' => 'John Doe', 'age' => 30, 'active' => true];
            $jsonString = json_encode($jsonData);
            $result = TransformerResult::successful($jsonString);

            $content = $result->getContent(parseJson: false);

            expect($content)->toBe($jsonString);
            expect($content)->toBeString();
        });

        test('returns parsed array when parseJson is true for JSON objects', function () {
            $jsonData = ['name' => 'John Doe', 'age' => 30, 'active' => true];
            $jsonString = json_encode($jsonData);
            $result = TransformerResult::successful($jsonString);

            $content = $result->getContent(parseJson: true);

            expect($content)->toBe($jsonData);
            expect($content)->toBeArray();
            expect($content['name'])->toBe('John Doe');
            expect($content['age'])->toBe(30);
            expect($content['active'])->toBe(true);
        });

        test('returns string content unchanged when parseJson is true but content is not JSON', function () {
            $stringContent = 'This is not JSON content';
            $result = TransformerResult::successful($stringContent);

            $content = $result->getContent(parseJson: true);

            expect($content)->toBe($stringContent);
            expect($content)->toBeString();
        });
    });

    describe('backward compatibility', function () {
        test('maintains existing behavior when no parameter provided', function () {
            $stringContent = 'Plain text content';
            $result = TransformerResult::successful($stringContent);

            $content = $result->getContent();

            expect($content)->toBe($stringContent);
            expect($content)->toBeString();
        });

        test('maintains existing behavior with JSON content when no parameter provided', function () {
            $jsonString = '{"name": "John", "age": 30}';
            $result = TransformerResult::successful($jsonString);

            $content = $result->getContent();

            expect($content)->toBe($jsonString);
            expect($content)->toBeString();
        });
    });

    describe('integration with metadata', function () {
        test('content retrieval works with metadata present', function () {
            $metadata = TransformerMetadata::make(
                model: 'test-model',
                provider: Provider::OPENAI,
                transformerClass: 'TestTransformer',
                context: ['source' => 'Original input content']
            );

            $jsonData = ['name' => 'Output content'];
            $result = TransformerResult::successful(json_encode($jsonData), $metadata);

            $rawContent = $result->getContent(parseJson: false);
            $parsedContent = $result->getContent(parseJson: true);
            $originalContent = $result->getMetadata()?->context['source'] ?? null;

            expect($rawContent)->toBeString();
            expect($parsedContent)->toBeArray();
            expect($parsedContent['name'])->toBe('Output content');
            expect($originalContent)->toBe('Original input content');
        });
    });

    describe('edge cases', function () {
        test('handles empty JSON object', function () {
            $result = TransformerResult::successful('{}');

            $rawContent = $result->getContent(parseJson: false);
            $parsedContent = $result->getContent(parseJson: true);

            expect($rawContent)->toBe('{}');
            expect($rawContent)->toBeString();
            expect($parsedContent)->toBe([]);
            expect($parsedContent)->toBeArray();
        });

        test('handles nested JSON structures', function () {
            $jsonData = [
                'user' => [
                    'name' => 'John Doe',
                    'profile' => [
                        'bio' => 'Software Developer',
                        'skills' => ['PHP', 'Laravel', 'JavaScript'],
                    ],
                ],
            ];
            $jsonString = json_encode($jsonData);
            $result = TransformerResult::successful($jsonString);

            $parsedContent = $result->getContent(parseJson: true);

            expect($parsedContent)->toBe($jsonData);
            expect($parsedContent['user']['name'])->toBe('John Doe');
            expect($parsedContent['user']['profile']['skills'])->toBe(['PHP', 'Laravel', 'JavaScript']);
        });

        test('handles null data gracefully', function () {
            $result = new TransformerResult(
                TransformerResult::STATUS_COMPLETED,
                null
            );

            $content = $result->getContent(parseJson: false);
            $parsedContent = $result->getContent(parseJson: true);

            expect($content)->toBeNull();
            expect($parsedContent)->toBeNull();
        });
    });
});
