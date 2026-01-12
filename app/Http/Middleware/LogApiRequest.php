<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Prepare request data for logging
        $requestData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'query' => $request->query->all(),
            'body' => $this->sanitizeBody($request->all()),
        ];

        // Log request in JSON format
        Log::info('API Request', $requestData);

        // Process request
        $response = $next($request);

        // Prepare response data for logging
        $responseData = [
            'status_code' => $response->getStatusCode(),
            'headers' => $this->sanitizeResponseHeaders($response->headers->all()),
        ];

        // Try to get response content if it's JSON
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $responseData['content'] = json_decode($response->getContent(), true);
        }

        // Log response in JSON format
        Log::info('API Response', array_merge([
            'method' => $request->method(),
            'path' => $request->path(),
        ], $responseData));

        return $response;
    }

    /**
     * Sanitize headers - remove sensitive information
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-csrf-token', 'x-xsrf-token'];
        $sanitized = [];

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveHeaders)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = is_array($value) ? $value[0] ?? $value : $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize body - remove sensitive information
     */
    private function sanitizeBody(array $body): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key', 'secret'];
        $sanitized = $body;

        foreach ($sanitized as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveFields)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeBody($value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize response headers
     */
    private function sanitizeResponseHeaders(array $headers): array
    {
        $sensitiveHeaders = ['set-cookie', 'authorization'];
        $sanitized = [];

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveHeaders)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = is_array($value) ? $value[0] ?? $value : $value;
            }
        }

        return $sanitized;
    }
}
