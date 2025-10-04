<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Handlers;

use Prism\Prism\ValueObjects\Media\Document;
use InvalidArgumentException;

class DocumentTransformerHandler extends MediaTransformerHandler
{
    protected const array SUPPORTED_INPUT_TYPES = [
        'url',
        'text',
        'fileId',
        'base64',
        'localPath',
        'rawContent',
        'storagePath',
    ];

    /**
     * {@inheritDoc}
     */
    public function handle(): Document
    {
        $this->validateHandler();

        try {
            return match ($this->inputType) {
                'url' => $this->handleUrl(),
                'text' => $this->handleText(),
                'base64' => $this->handleBase64(),
                'fileId' => $this->handleFileId(),
                'localPath' => $this->handleLocalPath(),
                'rawContent' => $this->handleRawContent(),
                'storagePath' => $this->handleStoragePath(),
                default => throw new InvalidArgumentException(
                    "Unsupported inputType: {$this->inputType}"
                ),
            };
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                "Failed to process document: {$e->getMessage()}", 0, $e
            );
        }
    }

    protected function handleLocalPath(): Document
    {
        if (! file_exists($this->path)) {
            throw new InvalidArgumentException(
                "File not found: {$this->path}"
            );
        }

        return Document::fromLocalPath(
            $this->path,
            $this->options['title'] ?? null
        );
    }

    protected function handleBase64(): Document
    {
        if (! $this->isValidBase64($this->path)) {
            throw new InvalidArgumentException(
                'Invalid base64 string provided'
            );
        }

        try {
            return Document::fromBase64(
                $this->path,
                $this->options['mimeType'] ?? null,
                $this->options['title'] ?? null
            );
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                "Invalid base64 content: {$e->getMessage()}", 0, $e
            );
        }
    }

    protected function handleText(): Document
    {
        return Document::fromText(
            $this->path,
            $this->options['title'] ?? null
        );
    }

    protected function handleUrl(): Document
    {
        return Document::fromUrl(
            $this->path,
            $this->options['title'] ?? null
        );
    }

    protected function handleStoragePath(): Document
    {
        return Document::fromStoragePath(
            $this->path,
            $this->options['disk'] ?? null,
            $this->options['title'] ?? null
        );
    }

    protected function handleRawContent(): Document
    {
        return Document::fromRawContent(
            $this->path,
            $this->options['mimeType'] ?? null,
            $this->options['title'] ?? null
        );
    }

    protected function handleFileId(): Document
    {
        return Document::fromFileId(
            $this->path,
            $this->options['title'] ?? null
        );
    }
}
