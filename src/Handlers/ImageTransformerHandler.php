<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Handlers;

use Prism\Prism\ValueObjects\Media\Image;
use InvalidArgumentException;

class ImageTransformerHandler extends MediaTransformerHandler
{
    protected const array SUPPORTED_INPUT_TYPES = [
        'url',
        'base64',
        'fileId',
        'localPath',
        'rawContent',
        'storagePath',
    ];

    /**
     * {@inheritDoc}
     */
    public function handle(): ?string
    {
        $this->validateHandler();

        try {
            return match ($this->inputType) {
                'url' => $this->handleUrl(),
                'base64' => $this->handleBase64(),
                'fileId' => $this->handleFileId(),
                'localPath' => $this->handleLocalPath(),
                'rawContent' => $this->handleRawContent(),
                'storagePath' => $this->handleStoragePath(),
                default => throw new InvalidArgumentException(
                    "Unsupported inputType: {$this->inputType}"
                ),
            };
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                "Failed to process image: {$e->getMessage()}", 0, $e
            );
        }
    }

    protected function handleLocalPath(): string
    {
        if (! file_exists($this->path)) {
            throw new InvalidArgumentException(
                "File not found: {$this->path}"
            );
        }

        return Image::fromLocalPath($this->path)->base64();
    }

    protected function handleBase64(): string
    {
        if (! $this->isValidBase64($this->path)) {
            throw new InvalidArgumentException(
                'Invalid base64 string provided'
            );
        }

        try {
            return Image::fromBase64($this->path)->base64();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(
                "Invalid base64 content: {$e->getMessage()}", 0, $e
            );
        }
    }

    protected function handleUrl(): string
    {
        return Image::fromUrl($this->path)->base64();
    }

    protected function handleFileId(): string
    {
        return Image::fromFileId($this->path)->base64();
    }

    protected function handleStoragePath(): string
    {
        return Image::fromStoragePath(
            $this->path,
            $this->options['disk'] ?? null
        )->base64();
    }

    protected function handleRawContent(): string
    {

        return Image::fromRawContent(
            $this->path,
            $this->options['mimeType'] ?? null
        )->base64();
    }
}
