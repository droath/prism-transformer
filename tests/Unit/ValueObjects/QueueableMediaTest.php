<?php

declare(strict_types=1);

use Droath\PrismTransformer\ValueObjects\QueueableMedia;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Document;

describe('QueueableMedia', function () {
    beforeEach(function () {
        // Create test image
        $this->testImageContent = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jINSAAAAQElEQVR4nGNgAAQAAAABAAA=';
        $this->testImagePath = tempnam(sys_get_temp_dir(), 'test_image').'.png';
        file_put_contents($this->testImagePath, base64_decode($this->testImageContent));

        // Create test document
        $this->testDocumentContent = 'This is a test document content.';
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

    describe('fromMedia factory method', function () {
        test('can wrap an Image object', function () {
            $image = Image::fromLocalPath($this->testImagePath);
            $queueable = QueueableMedia::fromMedia($image);

            expect($queueable)->toBeInstanceOf(QueueableMedia::class)
                ->and($queueable->type)->toBe('image')
                ->and($queueable->base64)->toBeString()
                ->and($queueable->base64)->not->toBeEmpty();
        });

        test('can wrap a Document object', function () {
            $document = Document::fromLocalPath($this->testDocumentPath);
            $queueable = QueueableMedia::fromMedia($document);

            expect($queueable)->toBeInstanceOf(QueueableMedia::class)
                ->and($queueable->type)->toBe('document')
                ->and($queueable->base64)->toBeString()
                ->and($queueable->base64)->not->toBeEmpty();
        });

        test('preserves mimeType when wrapping Image', function () {
            $image = Image::fromLocalPath($this->testImagePath);
            $queueable = QueueableMedia::fromMedia($image);

            expect($queueable->mimeType)->not->toBeNull();
        });

        test('preserves title when wrapping Document', function () {
            $document = Document::fromLocalPath($this->testDocumentPath, 'Test Title');
            $queueable = QueueableMedia::fromMedia($document);

            expect($queueable->title)->toBe('Test Title')
                ->and($document->documentTitle())->toBe('Test Title');
        });

        test('handles Document without title', function () {
            $document = Document::fromLocalPath($this->testDocumentPath);
            $queueable = QueueableMedia::fromMedia($document);

            expect($queueable->type)->toBe('document')
                ->and($queueable->title)->toBeNull();
        });
    });

    describe('toMedia reconstruction method', function () {
        test('can reconstruct Image from QueueableMedia', function () {
            $original = Image::fromLocalPath($this->testImagePath);
            $queueable = QueueableMedia::fromMedia($original);
            $reconstructed = $queueable->toMedia();

            expect($reconstructed)->toBeInstanceOf(Image::class)
                ->and($reconstructed->base64())->toBe($original->base64());
        });

        test('can reconstruct Document from QueueableMedia', function () {
            $original = Document::fromLocalPath($this->testDocumentPath);
            $queueable = QueueableMedia::fromMedia($original);
            $reconstructed = $queueable->toMedia();

            expect($reconstructed)->toBeInstanceOf(Document::class)
                ->and($reconstructed->base64())->toBe($original->base64());
        });

        test('preserves Image mimeType during reconstruction', function () {
            $original = Image::fromLocalPath($this->testImagePath);
            $queueable = QueueableMedia::fromMedia($original);
            $reconstructed = $queueable->toMedia();

            expect($reconstructed->mimeType())->toBe($original->mimeType());
        });

        test('preserves Document title during reconstruction', function () {
            $original = Document::fromLocalPath($this->testDocumentPath, 'My Document');
            $queueable = QueueableMedia::fromMedia($original);
            $reconstructed = $queueable->toMedia();

            expect($reconstructed->documentTitle())->toBe('My Document');
        });

        test('throws exception for invalid media type', function () {
            $queueable = new QueueableMedia('invalid', 'base64content');

            expect(fn () => $queueable->toMedia())
                ->toThrow(InvalidArgumentException::class, 'Unknown media type: invalid');
        });
    });

    describe('JSON serialization', function () {
        test('is JSON serializable', function () {
            $image = Image::fromLocalPath($this->testImagePath);
            $queueable = QueueableMedia::fromMedia($image);

            $json = json_encode($queueable);

            expect($json)->toBeString()
                ->and($json)->not->toBeFalse();
        });

        test('serializes all properties', function () {
            $image = Image::fromLocalPath($this->testImagePath);
            $queueable = QueueableMedia::fromMedia($image);

            $json = json_encode($queueable);
            $decoded = json_decode($json, true);

            expect($decoded)->toHaveKeys(['type', 'base64', 'mimeType', 'title'])
                ->and($decoded['type'])->toBe('image')
                ->and($decoded['base64'])->toBeString()
                ->and($decoded['mimeType'])->not->toBeNull();
        });

        test('serializes Document with title', function () {
            $document = Document::fromLocalPath($this->testDocumentPath, 'Test Doc');
            $queueable = QueueableMedia::fromMedia($document);

            $json = json_encode($queueable);
            $decoded = json_decode($json, true);

            expect($decoded['type'])->toBe('document')
                ->and($decoded['title'])->toBe('Test Doc');
        });

        test('can deserialize and reconstruct Media', function () {
            $original = Image::fromLocalPath($this->testImagePath);
            $queueable = QueueableMedia::fromMedia($original);

            // Simulate queue serialization/deserialization
            $json = json_encode($queueable);
            $decoded = json_decode($json, true);

            // Reconstruct QueueableMedia from decoded JSON
            $reconstructedQueueable = new QueueableMedia(
                $decoded['type'],
                $decoded['base64'],
                $decoded['mimeType'],
                $decoded['title']
            );

            $reconstructedMedia = $reconstructedQueueable->toMedia();

            expect($reconstructedMedia)->toBeInstanceOf(Image::class)
                ->and($reconstructedMedia->base64())->toBe($original->base64());
        });
    });

    describe('round-trip conversion', function () {
        test('Image survives full round-trip', function () {
            $original = Image::fromLocalPath($this->testImagePath);

            // Wrap -> Serialize -> Deserialize -> Unwrap
            $queueable = QueueableMedia::fromMedia($original);
            $json = json_encode($queueable);
            $decoded = json_decode($json, true);
            $reconstructedQueueable = new QueueableMedia(
                $decoded['type'],
                $decoded['base64'],
                $decoded['mimeType'],
                $decoded['title']
            );
            $reconstructed = $reconstructedQueueable->toMedia();

            expect($reconstructed)->toBeInstanceOf(Image::class)
                ->and($reconstructed->base64())->toBe($original->base64())
                ->and($reconstructed->mimeType())->toBe($original->mimeType());
        });

        test('Document survives full round-trip', function () {
            $original = Document::fromLocalPath($this->testDocumentPath, 'Important Document');

            // Wrap -> Serialize -> Deserialize -> Unwrap
            $queueable = QueueableMedia::fromMedia($original);
            $json = json_encode($queueable);
            $decoded = json_decode($json, true);
            $reconstructedQueueable = new QueueableMedia(
                $decoded['type'],
                $decoded['base64'],
                $decoded['mimeType'],
                $decoded['title']
            );
            $reconstructed = $reconstructedQueueable->toMedia();

            expect($reconstructed)->toBeInstanceOf(Document::class)
                ->and($reconstructed->base64())->toBe($original->base64())
                ->and($reconstructed->documentTitle())->toBe('Important Document');
        });
    });

    describe('readonly properties', function () {
        test('properties are readonly', function () {
            $image = Image::fromLocalPath($this->testImagePath);
            $queueable = QueueableMedia::fromMedia($image);

            expect(fn () => $queueable->type = 'modified')
                ->toThrow(Error::class);
        });
    });
});
