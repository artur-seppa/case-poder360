<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'local' => \App\Http\Middleware\EnsureLocalEnvironment::class,
        ]);

        $middleware->authenticateSessions();

        $middleware->api(append: [\App\Http\Middleware\UnescapeJsonUnicode::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Exceptions can be rendered before routing resolves a matching route
        // (e.g. an undefined URL), in which case no middleware group ever
        // runs — so UnescapeJsonUnicode (registered on the `api` group)
        // wouldn't apply. Setting the flags directly here keeps these
        // responses consistently unescaped regardless of that timing.
        $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($jsonOptions) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Você não tem permissão para acessar este recurso.'], 403, [], $jsonOptions);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($jsonOptions) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Recurso não encontrado.'], 404, [], $jsonOptions);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($jsonOptions) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Recurso não encontrado.'], 404, [], $jsonOptions);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($jsonOptions) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Os dados enviados são inválidos.',
                    'errors' => $e->errors(),
                ], 422, [], $jsonOptions);
            }
        });

        $exceptions->render(function (\ValueError $e, Request $request) use ($jsonOptions) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Parâmetro inválido.'], 422, [], $jsonOptions);
            }
        });
    })->create();
