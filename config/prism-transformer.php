<?php

use Droath\PrismTransformer\Enums\Provider;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | This is the default AI provider that will be used for transformations
    | when no provider is explicitly specified. Available providers are
    | defined in the Provider enum.
    |
    | Supported providers: "openai", "anthropic", "groq", "ollama", "gemini",
    | "mistral", "deepseek", "xai", "openrouter", "voyageai", "elevenlabs"
    |
    */

    'default_provider' => env('PRISM_TRANSFORMER_DEFAULT_PROVIDER', Provider::OPENAI),

    /*
    |--------------------------------------------------------------------------
    | Provider Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for each AI provider including default models and
    | provider-specific settings.
    |
    */

    'providers' => [
        'openai' => [
            'default_model' => env('PRISM_TRANSFORMER_OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => env('PRISM_TRANSFORMER_OPENAI_MAX_TOKENS', 4096),
            'temperature' => env('PRISM_TRANSFORMER_OPENAI_TEMPERATURE', 0.7),
        ],

        'anthropic' => [
            'default_model' => env('PRISM_TRANSFORMER_ANTHROPIC_MODEL', 'claude-3-5-haiku-20241022'),
            'max_tokens' => env('PRISM_TRANSFORMER_ANTHROPIC_MAX_TOKENS', 4096),
            'temperature' => env('PRISM_TRANSFORMER_ANTHROPIC_TEMPERATURE', 0.7),
        ],

        'groq' => [
            'default_model' => env('PRISM_TRANSFORMER_GROQ_MODEL', 'llama-3.1-8b'),
            'max_tokens' => env('PRISM_TRANSFORMER_GROQ_MAX_TOKENS', 4096),
            'temperature' => env('PRISM_TRANSFORMER_GROQ_TEMPERATURE', 0.7),
        ],

        'ollama' => [
            'default_model' => env('PRISM_TRANSFORMER_OLLAMA_MODEL', 'llama3.2:1b'),
            'base_url' => env('PRISM_TRANSFORMER_OLLAMA_BASE_URL', 'http://localhost:11434'),
            'timeout' => env('PRISM_TRANSFORMER_OLLAMA_TIMEOUT', 120),
        ],

        'gemini' => [
            'default_model' => env('PRISM_TRANSFORMER_GEMINI_MODEL', 'gemini-2.0'),
            'max_tokens' => env('PRISM_TRANSFORMER_GEMINI_MAX_TOKENS', 4096),
            'temperature' => env('PRISM_TRANSFORMER_GEMINI_TEMPERATURE', 0.7),
        ],

        'mistral' => [
            'default_model' => env('PRISM_TRANSFORMER_MISTRAL_MODEL', 'mistral-7b-instruct'),
            'max_tokens' => env('PRISM_TRANSFORMER_MISTRAL_MAX_TOKENS', 4096),
            'temperature' => env('PRISM_TRANSFORMER_MISTRAL_TEMPERATURE', 0.7),
        ],

        'deepseek' => [
            'default_model' => env('PRISM_TRANSFORMER_DEEPSEEK_MODEL', 'deepseek-chat'),
            'max_tokens' => env('PRISM_TRANSFORMER_DEEPSEEK_MAX_TOKENS', 4096),
            'temperature' => env('PRISM_TRANSFORMER_DEEPSEEK_TEMPERATURE', 0.7),
        ],

        'xai' => [
            'default_model' => env('PRISM_TRANSFORMER_XAI_MODEL', 'grok-beta'),
            'max_tokens' => env('PRISM_TRANSFORMER_XAI_MAX_TOKENS', 4096),
            'temperature' => env('PRISM_TRANSFORMER_XAI_TEMPERATURE', 0.7),
        ],

        'openrouter' => [
            'default_model' => env('PRISM_TRANSFORMER_OPENROUTER_MODEL', 'meta-llama/llama-3.2-1b-instruct:free'),
            'max_tokens' => env('PRISM_TRANSFORMER_OPENROUTER_MAX_TOKENS', 4096),
            'temperature' => env('PRISM_TRANSFORMER_OPENROUTER_TEMPERATURE', 0.7),
        ],

        'voyageai' => [
            'default_model' => env('PRISM_TRANSFORMER_VOYAGEAI_MODEL', 'voyage-3-lite'),
            'max_tokens' => env('PRISM_TRANSFORMER_VOYAGEAI_MAX_TOKENS', 4096),
        ],

        'elevenlabs' => [
            'default_model' => env('PRISM_TRANSFORMER_ELEVENLABS_MODEL', 'eleven_turbo_v2_5'),
            'voice_settings' => [
                'stability' => env('PRISM_TRANSFORMER_ELEVENLABS_STABILITY', 0.5),
                'similarity_boost' => env('PRISM_TRANSFORMER_ELEVENLABS_SIMILARITY', 0.75),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Fetching Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for content fetching operations including HTTP timeouts,
    | retry policies, and URL validation settings.
    |
    */

    'content_fetcher' => [
        'timeout' => env('PRISM_TRANSFORMER_HTTP_TIMEOUT', 30),
        'max_redirects' => env('PRISM_TRANSFORMER_MAX_REDIRECTS', 5),
        'connect_timeout' => env('PRISM_TRANSFORMER_CONNECT_TIMEOUT', 10),
        'user_agent' => env('PRISM_TRANSFORMER_USER_AGENT', 'PrismTransformer/1.0'),

        'retry' => [
            'max_attempts' => env('PRISM_TRANSFORMER_RETRY_ATTEMPTS', 3),
            'delay' => env('PRISM_TRANSFORMER_RETRY_DELAY', 1000), // milliseconds
        ],

        'validation' => [
            'blocked_domains' => [],
            'allowed_schemes' => ['http', 'https'],
            'allow_localhost' => env('PRISM_TRANSFORMER_ALLOW_LOCALHOST', false),
            'max_content_length' => env('PRISM_TRANSFORMER_MAX_CONTENT_LENGTH', 10485760), // 10MB
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transformation Settings
    |--------------------------------------------------------------------------
    |
    | Global settings for transformation operations including async handling,
    | result validation, and metadata tracking.
    |
    */

    'transformation' => [
        'async_queue' => env('PRISM_TRANSFORMER_ASYNC_QUEUE', 'default'),
        'queue_connection' => env('PRISM_TRANSFORMER_QUEUE_CONNECTION'),
        'timeout' => env('PRISM_TRANSFORMER_TIMEOUT', 60),
        'tries' => env('PRISM_TRANSFORMER_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for caching transformation results and content to improve
    | performance and reduce API calls.
    |
    */

    'cache' => [
        'enabled' => env('PRISM_TRANSFORMER_CACHE_ENABLED', true),
        'store' => env('PRISM_TRANSFORMER_CACHE_STORE', 'default'),
        'prefix' => env('PRISM_TRANSFORMER_CACHE_PREFIX', 'prism_transformer'),

        'ttl' => [
            'content_fetch' => env('PRISM_TRANSFORMER_CACHE_TTL_CONTENT', 1800), // 30 minutes
            'transformer_data' => env('PRISM_TRANSFORMER_CACHE_TTL_TRANSFORMATIONS', 3600), // 1 hour
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for rate limiting transformation requests to prevent
    | system overload and ensure fair usage across users and applications.
    |
    */

    'rate_limiting' => [
        'enabled' => env('PRISM_RATE_LIMITING_ENABLED', false),
        'max_attempts' => env('PRISM_RATE_LIMIT_ATTEMPTS', 60),
        'decay_minutes' => env('PRISM_RATE_LIMIT_DECAY', 1),
        'key_prefix' => env('PRISM_RATE_LIMIT_PREFIX', 'prism_rate_limit'),
    ],
];
