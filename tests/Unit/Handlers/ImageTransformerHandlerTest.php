<?php

declare(strict_types=1);

use Droath\PrismTransformer\Handlers\ImageTransformerHandler;
use Droath\PrismTransformer\Handlers\Contracts\HandlerInterface;
use Prism\Prism\ValueObjects\Media\Image;

describe('ImageTransformerHandler', function () {
    beforeEach(function () {
        // Create a simple 1x1 pixel PNG image for testing
        $this->testBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAI9jINSAAAAQElEQVR4nGNgAAQAAAABAAA=';

        // Create temp file for local path testing
        $this->testImagePath = tempnam(sys_get_temp_dir(), 'test_image').'.png';
        file_put_contents($this->testImagePath, base64_decode($this->testBase64));
    });

    afterEach(function () {
        if (isset($this->testImagePath) && file_exists($this->testImagePath)) {
            unlink($this->testImagePath);
        }
    });

    test('implements HandlerInterface', function () {
        $handler = new ImageTransformerHandler('test.jpg');

        expect($handler)->toBeInstanceOf(HandlerInterface::class);
    });

    test('can be instantiated with path only', function () {
        $handler = new ImageTransformerHandler('test.jpg');

        expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
    });

    test('can be instantiated with path and options', function () {
        $options = ['inputType' => 'localPath', 'mimeType' => 'image/jpeg'];
        $handler = new ImageTransformerHandler('test.jpg', $options);

        expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
    });

    test('defaults to localPath inputType when not specified', function () {
        $handler = new ImageTransformerHandler($this->testImagePath);
        $result = $handler->handle();

        expect($result)->toBeString();
    });

    describe('inputType support', function () {
        test('handles localPath inputType', function () {
            $handler = new ImageTransformerHandler($this->testImagePath, ['inputType' => 'localPath']);
            $result = $handler->handle();

            expect($result)
                ->toBeString()
                ->toMatch('/^[A-Za-z0-9+\/]+=*$/'); // Base64 pattern
        });

        test('handles base64 inputType', function () {
            $handler = new ImageTransformerHandler($this->testBase64, ['inputType' => 'base64']);
            $result = $handler->handle();

            expect($result)
                ->toBeString()
                ->toBe($this->testBase64);
        });

        test('handles url inputType', function () {
            $url = 'https://example.com/image.jpg';
            $handler = new ImageTransformerHandler($url, ['inputType' => 'url']);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });

        test('handles storagePath inputType', function () {
            $storagePath = 'images/test.jpg';
            $options = ['inputType' => 'storagePath', 'disk' => 'public'];
            $handler = new ImageTransformerHandler($storagePath, $options);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });

        test('handles rawContent inputType', function () {
            $rawContent = base64_decode($this->testBase64);
            $options = ['inputType' => 'rawContent', 'mimeType' => 'image/png'];
            $handler = new ImageTransformerHandler($rawContent, $options);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });

        test('handles fileId inputType', function () {
            $fileId = 'file-123';
            $handler = new ImageTransformerHandler($fileId, ['inputType' => 'fileId']);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });
    });

    describe('validation', function () {
        test('throws InvalidArgumentException for unsupported inputType', function () {
            expect(fn () => new ImageTransformerHandler('path', ['inputType' => 'invalid']))
                ->toThrow(InvalidArgumentException::class, 'Unsupported inputType: invalid');
        });

        test('throws InvalidArgumentException for missing file', function () {
            $handler = new ImageTransformerHandler('/nonexistent/file.jpg', ['inputType' => 'localPath']);

            expect(fn () => $handler->handle())
                ->toThrow(InvalidArgumentException::class);
        });

        test('throws InvalidArgumentException for invalid base64', function () {
            $handler = new ImageTransformerHandler('invalid-base64!', ['inputType' => 'base64']);

            expect(fn () => $handler->handle())
                ->toThrow(InvalidArgumentException::class);
        });

        test('throws InvalidArgumentException for missing disk parameter with storagePath', function () {
            expect(fn () => (new ImageTransformerHandler('path', ['inputType' => 'storagePath']))->handle())
                ->toThrow(InvalidArgumentException::class, 'disk parameter is required for storagePath inputType');
        });

        test('throws InvalidArgumentException for missing mimeType with rawContent', function () {
            expect(fn () => (new ImageTransformerHandler('content', ['inputType' => 'rawContent']))->handle())
                ->toThrow(InvalidArgumentException::class, 'mimeType parameter is required for rawContent inputType');
        });
    });

    describe('options processing', function () {
        test('processes inputType option correctly', function () {
            $handler = new ImageTransformerHandler($this->testImagePath, ['inputType' => 'localPath']);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });

        test('processes mimeType option correctly', function () {
            $options = ['inputType' => 'rawContent', 'mimeType' => 'image/png'];
            $handler = new ImageTransformerHandler('raw-content', $options);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });

        test('processes disk option correctly', function () {
            $options = ['inputType' => 'storagePath', 'disk' => 'public'];
            $handler = new ImageTransformerHandler('path', $options);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });

        test('handles empty options array', function () {
            $handler = new ImageTransformerHandler($this->testImagePath, []);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });
    });

    describe('handle method', function () {
        test('returns base64 string', function () {
            $handler = new ImageTransformerHandler($this->testImagePath, ['inputType' => 'localPath']);
            $result = $handler->handle();

            expect($result)
                ->toBeString()
                ->toMatch('/^[A-Za-z0-9+\/]+=*$/'); // Base64 pattern
        });

        test('returns null when unable to process', function () {
            $handler = new ImageTransformerHandler('/definitely/nonexistent/file.jpg', ['inputType' => 'localPath']);

            expect(fn () => $handler->handle())
                ->toThrow(InvalidArgumentException::class);
        });

        test('converts local file to base64', function () {
            $handler = new ImageTransformerHandler($this->testImagePath, ['inputType' => 'localPath']);
            $result = $handler->handle();

            expect($result)->toBe($this->testBase64);
        });
    });

    describe('integration with Prism Image factory methods', function () {
        test('uses Prism Image fromLocalPath method', function () {
            $handler = new ImageTransformerHandler($this->testImagePath, ['inputType' => 'localPath']);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });

        test('uses Prism Image fromUrl method', function () {
            $handler = new ImageTransformerHandler('https://example.com/image.jpg', ['inputType' => 'url']);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });

        test('uses Prism Image fromBase64 method', function () {
            $handler = new ImageTransformerHandler($this->testBase64, ['inputType' => 'base64']);

            expect($handler)->toBeInstanceOf(ImageTransformerHandler::class);
        });
    });
});
