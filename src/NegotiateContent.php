<?php

declare(strict_types=1);

namespace Snicco\Middleware\Negotiation;

use Middlewares\ContentLanguage;
use Middlewares\ContentType;
use Psr\Http\Message\ResponseInterface;
use Snicco\Component\HttpRouting\Http\Psr7\Request;
use Snicco\Component\HttpRouting\Middleware\Middleware;
use Snicco\Component\HttpRouting\Middleware\NextMiddleware;
use Snicco\Component\Psr7ErrorHandler\HttpException;

final class NegotiateContent extends Middleware
{
    private array $content_types;

    /**
     * @var string[]
     */
    private array $languages = [];

    /**
     * @var string[]
     */
    private array $charsets = [];

    /**
     * @param string[]      $languages
     * @param string[]|null $charsets
     * @param null|array<
     *     string,
     *     array{extension?: string[], mime-type?: string[], charset?: bool
     * }
     * > $content_types
     */
    public function __construct(array $languages, array $content_types = null, array $charsets = null)
    {
        $this->languages = $languages;
        $this->charsets = $charsets ?: ['UTF-8'];
        $this->content_types = $content_types ?: $this->defaultConfiguration();
    }

    protected function handle(Request $request, NextMiddleware $next): ResponseInterface
    {
        $content_type = new ContentType($this->content_types);
        $content_type->charsets($this->charsets);
        $content_type->errorResponse($this->responseFactory());
        $content_type->nosniff(true);

        $language = new ContentLanguage($this->languages);

        $response = $content_type->process($request, $this->next($language, $next));

        if (406 === $response->getStatusCode()) {
            throw new HttpException(406, sprintf('Failed content negotiation for path [%s].', $request->path()));
        }

        return $response;
    }

    /**
     * @return array{html: array{extension: string[], mime-type: string[], charset: true}, txt: array{extension: string[], mime-type: string[], charset: true}, json: array{extension: string[], mime-type: string[], charset: true}}
     */
    private function defaultConfiguration(): array
    {
        return [
            'html' => [
                'extension' => ['html', 'php'],
                'mime-type' => ['text/html'],
                'charset' => true,
            ],
            'txt' => [
                'extension' => ['txt'],
                'mime-type' => ['text/plain'],
                'charset' => true,
            ],
            'json' => [
                'extension' => ['json'],
                'mime-type' => ['application/json'],
                'charset' => true,
            ],
        ];
    }

    private function next(ContentLanguage $language, NextMiddleware $next): NextMiddleware
    {
        return new NextMiddleware(fn (Request $request): ResponseInterface => $language->process($request, $next));
    }
}
