<?php

declare(strict_types=1);

namespace Droath\PrismTransformer\Abstract;

use Prism\Prism\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Illuminate\Database\Eloquent\Model;
use Droath\PrismTransformer\Enums\Provider;
use Droath\PrismTransformer\TransformationResult;
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
        return Provider::OPENAI;
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
    public function execute(string $content): TransformationResult
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
    protected function afterTransform(TransformationResult $result): void {}

    /**
     * Prism PHP integration for LLM transformation.
     *
     * This method uses Prism::structured() to perform the actual
     * AI transformation using the configured provider and model.
     */
    protected function performTransformation(string $content): TransformationResult
    {
        try {
            // TODO: Implement actual Prism PHP integration when package is available
            // For now, this is a placeholder implementation that demonstrates the structure

            // Note: Schema mapping will be implemented in Phase 2
            // $schema = app(ModelSchemaMapperInterface::class)->mapModelToSchema($this->outputFormat());

            $provider = $this->provider()->toPrism();

            $response = Prism::structured()
                ->using($provider, $this->model())
                ->withPrompt($this->prompt())
                ->asStructured();

            return new TransformationResult(
                status: 'completed',
                data: $response,
                metadata: [
                    'model' => $this->model(),
                    'tokens' => $response['tokens'],
                    'provider' => $provider->value,
                ]
            );
        } catch (\Exception $e) {
            return new TransformationResult(
                status: 'failed',
                data: null,
                errors: [$e->getMessage()]
            );
        }
    }
}
