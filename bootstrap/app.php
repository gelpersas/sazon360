<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        // En producción corre detrás del proxy de EasyPanel (termina TLS
        // antes de reenviar al contenedor) — sin esto, Laravel no confía en
        // X-Forwarded-Proto y genera URLs de assets en http:// aunque
        // APP_URL sea https://, rompiendo CSS/JS/fuentes en el navegador
        // (bloqueado por mixed content / el proxy no enruta HTTP plano).
        // '*' porque la IP interna del proxy de EasyPanel no es fija/conocida
        // de antemano — mismo criterio que usan Forge/Vapor por defecto.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
