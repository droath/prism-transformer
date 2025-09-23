<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Handlers;

use InvalidArgumentException;
use Droath\PrismTransformer\Handlers\Contracts\HandlerInterface;

abstract class MediaTransformerHandler implements HandlerInterface
{
    protected const string DEFAULT_INPUT_TYPE = 'localPath';

    /**
     * Define supported input types.
     */
    protected const array SUPPORTED_INPUT_TYPES = [];

    protected string $inputType;

    public function __construct(
        protected string $path,
        protected array $options = []
    ) {
        $this->inputType = $this->options['inputType'] ?? static::DEFAULT_INPUT_TYPE;

        if (! in_array($this->inputType, static::SUPPORTED_INPUT_TYPES, true)) {
            throw new InvalidArgumentException(
                "Unsupported inputType: {$this->inputType}"
            );
        }
    }

    protected function validateHandler(): void
    {
        if (
            $this->inputType === 'storagePath'
            && ! isset($this->options['disk'])
        ) {
            throw new InvalidArgumentException(
                'disk parameter is required for storagePath inputType'
            );
        }

        if (
            $this->inputType === 'rawContent'
            && ! isset($this->options['mimeType'])
        ) {
            throw new InvalidArgumentException(
                'mimeType parameter is required for rawContent inputType'
            );
        }
    }

    protected function isValidBase64(string $data): bool
    {
        if (! preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $data)) {
            return false;
        }

        return base64_decode($data, true) !== false;
    }
}
