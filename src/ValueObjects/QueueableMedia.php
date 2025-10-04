<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\ValueObjects;

use Prism\Prism\ValueObjects\Media\Media;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Document;
use JsonSerializable;

/**
 * Queue-safe wrapper for Media objects.
 *
 * Wraps Media objects (Image/Document) for Laravel queue serialization.
 * Media objects contain binary data that can't be JSON-encoded, so we
 * convert to base64 for queuing and reconstruct on the job side.
 */
class QueueableMedia implements JsonSerializable
{
    /**
     * @param string $type The media type ('image' or 'document')
     * @param string $base64 The base64-encoded media content
     * @param string|null $mimeType Optional MIME type for reconstruction
     * @param string|null $title Optional title (for documents)
     */
    public function __construct(
        public readonly string $type,
        public readonly string $base64,
        public readonly ?string $mimeType = null,
        public readonly ?string $title = null
    ) {}

    /**
     * Create from a Media object.
     */
    public static function fromMedia(Media $media): self
    {
        return new self(
            type: $media instanceof Image ? 'image' : 'document',
            base64: $media->base64(),
            mimeType: $media->mimeType(),
            title: $media instanceof Document ? $media->documentTitle() : null
        );
    }

    /**
     * Reconstruct the original Media object.
     */
    public function toMedia(): Media
    {
        return match ($this->type) {
            'image' => $this->mimeType
                ? Image::fromBase64($this->base64, $this->mimeType)
                : Image::fromBase64($this->base64),
            'document' => Document::fromBase64($this->base64, $this->mimeType, $this->title),
            default => throw new \InvalidArgumentException("Unknown media type: {$this->type}"),
        };
    }

    /**
     * Make the object JSON-serializable for queue.
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'base64' => $this->base64,
            'mimeType' => $this->mimeType,
            'title' => $this->title,
        ];
    }
}
