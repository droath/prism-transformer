<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Abstract;

use Prism\Prism\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Illuminate\Database\Eloquent\Model;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\Services\ConfigurationService;
use Droath\PrismTransformer\ValueObjects\TransformerResult;
use Droath\PrismTransformer\ValueObjects\TransformerMetadata;
use Droath\PrismTransformer\Contracts\TransformerInterface;

/**
 * Foundation abstract class providing a core LLM transformation.
 *
 * This class implements the template method pattern for AI-powered
 * transformations using Prism PHP integration. Concrete transformers
 * should extend this class and implement the abstract methods to
 * define their specific transformation behavior.
 */
abstract class BaseTransformer implements TransformerInterface
{
    public function __construct(
        protected ConfigurationService $configuration
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return static::class;
    }

    /**
     * {@inheritDoc}
     */
    public function provider(): Provider
    {
        return $this->configuration->getDefaultProvider();
    }

    /**
     * {@inheritDoc}
     */
    public function model(): string
    {
        return $this->provider()->defaultModel();
    }

    /**
     * {@inheritDoc}
     */
    public function outputFormat(ObjectSchema|Model $format): ?ObjectSchema
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $content): TransformerResult
    {
        $this->beforeTransform($content);
        $result = $this->performTransformation($content);
        $this->afterTransform($result);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function cacheId(): string
    {
        return hash('sha256', serialize(array_filter([
            static::class,
            $this->prompt(),
            $this->provider()->value,
            $this->model(),
        ])));
    }

    /**
     * Prism PHP integration for LLM transformation.
     *
     * This method uses Prism::structured() to perform the actual
     * AI transformation using the configured provider and model.
     *
     * @param string $content
     *   The content to transform.
     *
     * @return TransformerResult
     *   The transformation results with data and metadata.
     */
    protected function performTransformation(string $content): TransformerResult
    {
        try {
            $provider = $this->provider()->toPrism();

            if ($provider === null) {
                throw new \InvalidArgumentException(
                    'Invalid provider'
                );
            }

            $structuredBuilder = Prism::structured()
                ->using($provider, $this->model())
                ->withPrompt($this->prompt());

            // Note: Schema mapping will be implemented in Phase 2
            // if ($this->outputFormat() !== null) {
            //     $structuredBuilder = $structuredBuilder->withSchema($this->outputFormat());
            // }

            $response = $structuredBuilder->asStructured();

            return TransformerResult::successful(
                $response->text
            );

        } catch (\Throwable $e) {
            return TransformerResult::failed(
                [$e->getMessage()],
                TransformerMetadata::make(
                    $this->model(),
                    $this->provider(),
                    static::class
                )
            );
        }
    }

    /**
     * Hook called before the transformation begins.
     *
     * Override in concrete classes or traits for custom pre-processing
     * like input validation, content sanitization, etc.
     */
    protected function beforeTransform(string $content): void {}

    /**
     * Hook called after transformation completes.
     *
     * Override in concrete classes or traits for custom post-processing
     * like result validation, caching, logging, etc.
     */
    protected function afterTransform(TransformerResult $result): void {}
}
