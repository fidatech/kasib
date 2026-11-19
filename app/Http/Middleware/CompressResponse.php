<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pages leave the server gzipped. A list is 100 to 350 KB of HTML and a tenth of that compressed,
 * which is the difference between a page that opens at once and one that takes seconds on a slow
 * line. The web server does not compress every page of the application by itself, so it is done here.
 */
class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse || ! function_exists('gzencode')
            || $response->headers->has('Content-Encoding') || ! in_array('gzip', array_map('trim', explode(',', strtolower((string) $request->headers->get('Accept-Encoding')))), true)) {
            return $response;
        }
        $type = strtolower((string) $response->headers->get('Content-Type'));
        $content = $response->getContent();
        if (! is_string($content) || strlen($content) < 1400 || ! ($type === '' || str_contains($type, 'text/') || str_contains($type, 'json') || str_contains($type, 'xml'))) {
            return $response;
        }
        $packed = gzencode($content, 6);
        if ($packed === false) {
            return $response;
        }
        $response->setContent($packed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding', false);
        $response->headers->set('Content-Length', (string) strlen($packed));

        return $response;
    }
}
