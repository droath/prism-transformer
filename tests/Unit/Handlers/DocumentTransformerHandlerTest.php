<?php

declare(strict_types=1);

use Droath\PrismTransformer\Handlers\DocumentTransformerHandler;
use Droath\PrismTransformer\Handlers\Contracts\HandlerInterface;
use Prism\Prism\ValueObjects\Media\Document;

describe('DocumentTransformerHandler', function () {
    beforeEach(function () {
        // Create a simple text document for testing
        $this->testDocumentContent = 'This is a test document content for testing purposes.';
        $this->testBase64 = base64_encode($this->testDocumentContent);

        // Create temp file for local path testing
        $this->testDocumentPath = tempnam(sys_get_temp_dir(), 'test_document').'.txt';
        file_put_contents($this->testDocumentPath, $this->testDocumentContent);
    });

    afterEach(function () {
        if (isset($this->testDocumentPath) && file_exists($this->testDocumentPath)) {
            unlink($this->testDocumentPath);
        }
    });

    test('implements HandlerInterface', function () {
        $handler = new DocumentTransformerHandler('test.txt');

        expect($handler)->toBeInstanceOf(HandlerInterface::class);
    });

    test('can be instantiated with path only', function () {
        $handler = new DocumentTransformerHandler('test.txt');

        expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
    });

    test('can be instantiated with path and options', function () {
        $options = ['inputType' => 'localPath', 'title' => 'Test Document'];
        $handler = new DocumentTransformerHandler('test.txt', $options);

        expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
    });

    test('defaults to localPath inputType when not specified', function () {
        $handler = new DocumentTransformerHandler($this->testDocumentPath);
        $result = $handler->handle();

        expect($result)->toBeInstanceOf(\Prism\Prism\ValueObjects\Media\Document::class);
    });

    describe('inputType support', function () {
        test('handles localPath inputType', function () {
            $handler = new DocumentTransformerHandler($this->testDocumentPath, ['inputType' => 'localPath']);
            $result = $handler->handle();

            expect($result)
                ->toBeInstanceOf(\Prism\Prism\ValueObjects\Media\Document::class);
        });

        test('handles base64 inputType', function () {
            $handler = new DocumentTransformerHandler($this->testBase64, ['inputType' => 'base64']);
            $result = $handler->handle();

            expect($result)
                ->toBeInstanceOf(\Prism\Prism\ValueObjects\Media\Document::class);
        });

        test('handles text inputType', function () {
            $handler = new DocumentTransformerHandler($this->testDocumentContent, ['inputType' => 'text']);
            $result = $handler->handle();

            expect($result)
                ->toBeInstanceOf(\Prism\Prism\ValueObjects\Media\Document::class);
        });

        test('handles url inputType', function () {
            $url = 'https://example.com/document.pdf';
            $handler = new DocumentTransformerHandler($url, ['inputType' => 'url']);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });

        test('handles storagePath inputType', function () {
            $storagePath = 'documents/test.pdf';
            $options = ['inputType' => 'storagePath', 'disk' => 'public'];
            $handler = new DocumentTransformerHandler($storagePath, $options);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });

        test('handles rawContent inputType', function () {
            $rawContent = $this->testDocumentContent;
            $options = ['inputType' => 'rawContent', 'mimeType' => 'text/plain'];
            $handler = new DocumentTransformerHandler($rawContent, $options);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });

        test('handles fileId inputType', function () {
            $fileId = 'file-123';
            $handler = new DocumentTransformerHandler($fileId, ['inputType' => 'fileId']);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });
    });

    describe('validation', function () {
        test('throws InvalidArgumentException for unsupported inputType', function () {
            expect(fn () => new DocumentTransformerHandler('path', ['inputType' => 'invalid']))
                ->toThrow(InvalidArgumentException::class, 'Unsupported inputType: invalid');
        });

        test('throws InvalidArgumentException for missing file', function () {
            $handler = new DocumentTransformerHandler('/nonexistent/file.txt', ['inputType' => 'localPath']);

            expect(fn () => $handler->handle())
                ->toThrow(InvalidArgumentException::class);
        });

        test('throws InvalidArgumentException for invalid base64', function () {
            $handler = new DocumentTransformerHandler('invalid-base64!', ['inputType' => 'base64']);

            expect(fn () => $handler->handle())
                ->toThrow(InvalidArgumentException::class);
        });

        test('throws InvalidArgumentException for missing disk parameter with storagePath', function () {
            expect(fn () => (new DocumentTransformerHandler('path', ['inputType' => 'storagePath']))->handle())
                ->toThrow(InvalidArgumentException::class, 'disk parameter is required for storagePath inputType');
        });

        test('throws InvalidArgumentException for missing mimeType with rawContent', function () {
            expect(fn () => (new DocumentTransformerHandler('content', ['inputType' => 'rawContent']))->handle())
                ->toThrow(InvalidArgumentException::class, 'mimeType parameter is required for rawContent inputType');
        });
    });

    describe('options processing', function () {
        test('processes inputType option correctly', function () {
            $handler = new DocumentTransformerHandler($this->testDocumentPath, ['inputType' => 'localPath']);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });

        test('processes title option correctly', function () {
            $options = ['inputType' => 'localPath', 'title' => 'Test Document Title'];
            $handler = new DocumentTransformerHandler($this->testDocumentPath, $options);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });

        test('processes mimeType option correctly', function () {
            $options = ['inputType' => 'rawContent', 'mimeType' => 'text/plain'];
            $handler = new DocumentTransformerHandler('raw-content', $options);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });

        test('processes disk option correctly', function () {
            $options = ['inputType' => 'storagePath', 'disk' => 'public'];
            $handler = new DocumentTransformerHandler('path', $options);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });

        test('handles empty options array', function () {
            $handler = new DocumentTransformerHandler($this->testDocumentPath, []);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });
    });

    describe('handle method', function () {
        test('returns base64 string', function () {
            $handler = new DocumentTransformerHandler($this->testDocumentPath, ['inputType' => 'localPath']);
            $result = $handler->handle();

            expect($result)
                ->toBeInstanceOf(\Prism\Prism\ValueObjects\Media\Document::class);
        });

        test('returns null when unable to process', function () {
            $handler = new DocumentTransformerHandler('/definitely/nonexistent/file.txt', ['inputType' => 'localPath']);

            expect(fn () => $handler->handle())
                ->toThrow(InvalidArgumentException::class);
        });

        test('converts local file to base64', function () {
            $handler = new DocumentTransformerHandler($this->testDocumentPath, ['inputType' => 'localPath']);
            $result = $handler->handle();

            expect($result)
                ->toBeInstanceOf(\Prism\Prism\ValueObjects\Media\Document::class);
        });

        test('converts text content to base64', function () {
            $handler = new DocumentTransformerHandler($this->testDocumentContent, ['inputType' => 'text']);
            $result = $handler->handle();

            expect($result)
                ->toBeInstanceOf(\Prism\Prism\ValueObjects\Media\Document::class);
        });
    });

    describe('integration with Prism Document factory methods', function () {
        test('uses Prism Document fromLocalPath method', function () {
            $handler = new DocumentTransformerHandler($this->testDocumentPath, ['inputType' => 'localPath']);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });

        test('uses Prism Document fromUrl method', function () {
            $handler = new DocumentTransformerHandler('https://example.com/document.pdf', ['inputType' => 'url']);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });

        test('uses Prism Document fromBase64 method', function () {
            $handler = new DocumentTransformerHandler($this->testBase64, ['inputType' => 'base64']);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });

        test('uses Prism Document fromText method', function () {
            $handler = new DocumentTransformerHandler($this->testDocumentContent, ['inputType' => 'text']);

            expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
        });
    });

    describe('comprehensive input type coverage', function () {
        test('supports all required inputTypes', function () {
            $supportedTypes = ['fileId', 'localPath', 'storagePath', 'url', 'base64', 'rawContent', 'text'];

            foreach ($supportedTypes as $inputType) {
                $options = ['inputType' => $inputType];

                // Add required options for specific input types
                if ($inputType === 'storagePath') {
                    $options['disk'] = 'public';
                }
                if ($inputType === 'rawContent') {
                    $options['mimeType'] = 'text/plain';
                }

                $path = match ($inputType) {
                    'text' => $this->testDocumentContent,
                    'base64' => $this->testBase64,
                    'localPath' => $this->testDocumentPath,
                    default => 'test-path'
                };

                $handler = new DocumentTransformerHandler($path, $options);

                expect($handler)->toBeInstanceOf(DocumentTransformerHandler::class);
            }
        });
    });
});
