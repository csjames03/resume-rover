<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side defenses: a nonce-based Content Security Policy (only scripts
 * this app emitted can run), no framing, no MIME sniffing, and HSTS over HTTPS.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Vite adds this nonce to every tag it renders; app.blade.php adds it to the inline script.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $devServer = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$devServer],
            // Radix, Sonner and Inertia's progress bar inject <style> tags at runtime.
            'style-src' => ["'self'", "'unsafe-inline'", ...$devServer],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:', ...$devServer],
            'connect-src' => ["'self'", ...$devServer, ...$this->websockets($devServer)],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $name) => $name.' '.implode(' ', $sources))
            ->implode('; ');
    }

    /**
     * While `npm run dev` is running, assets come from the Vite server.
     *
     * @return list<string>
     */
    private function viteDevServer(): array
    {
        if (! Vite::isRunningHot()) {
            return [];
        }

        $url = trim((string) file_get_contents(Vite::hotFile()));

        return $url === '' ? [] : [$url];
    }

    /**
     * Vite's hot reload talks over a websocket on the same host and port.
     *
     * @param  list<string>  $origins
     * @return list<string>
     */
    private function websockets(array $origins): array
    {
        return array_map(fn (string $origin) => preg_replace('#^http#', 'ws', $origin) ?? $origin, $origins);
    }
}
