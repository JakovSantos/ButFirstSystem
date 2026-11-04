<?php

declare(strict_types=1);

namespace App\Core;

use Bramus\Router\Router as BramusRouter;

/**
 * Thin static wrapper around bramus/router.
 *
 *  - 'Controller@method' strings resolve to [new App\Controllers\Controller(), 'method'].
 *  - Middleware classes (App\Middleware\*) can be attached with Router::middleware().
 */
final class Router
{
    private static ?BramusRouter $router = null;

    public static function instance(): BramusRouter
    {
        return self::$router ??= new BramusRouter();
    }

    public static function get(string $pattern, callable|string $handler): void
    {
        self::instance()->get($pattern, self::resolve($handler));
    }

    public static function post(string $pattern, callable|string $handler): void
    {
        self::instance()->post($pattern, self::resolve($handler));
    }

    public static function put(string $pattern, callable|string $handler): void
    {
        self::instance()->put($pattern, self::resolve($handler));
    }

    public static function delete(string $pattern, callable|string $handler): void
    {
        self::instance()->delete($pattern, self::resolve($handler));
    }

    /**
     * Register before-route middleware.
     *
     * @param string $methods  e.g. 'GET|POST' or 'GET|POST|PUT|DELETE'
     * @param string $pattern  e.g. '/inventory.*'
     * @param class-string<\App\Middleware\Middleware>|callable $middleware
     */
    public static function middleware(string $methods, string $pattern, string|callable $middleware): void
    {
        $callable = is_string($middleware)
            ? static function (...$params) use ($middleware): void {
                (new $middleware())->handle(Request::capture());
            }
            : $middleware;

        self::instance()->before($methods, $pattern, $callable);
    }

    public static function set404(callable $handler): void
    {
        self::instance()->set404($handler);
    }

    public static function run(): void
    {
        self::instance()->run();
    }

    private static function resolve(callable|string $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        if (! str_contains($handler, '@')) {
            throw new \InvalidArgumentException("Route handler '{$handler}' must use Controller@method.");
        }

        [$controller, $method] = explode('@', $handler, 2);
        $class = 'App\\Controllers\\' . $controller;

        return static function (...$params) use ($class, $method): void {
            if (! class_exists($class) || ! method_exists($class, $method)) {
                throw new \RuntimeException("Handler {$class}@{$method} not found.");
            }
            (new $class())->{$method}(...$params);
        };
    }
}
