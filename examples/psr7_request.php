<?php

declare(strict_types=1);

/**
 * Example: building PSR-7 HTTP messages with laminas-diactoros.
 *
 * Run from the laminas-diactoros project root:
 *   php examples/psr7_request.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Laminas\Diactoros\Request;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Uri;
use Laminas\Diactoros\Stream;

// --- Build an outgoing HTTP request (PSR-7 RequestInterface) ---
$uri = new Uri('https://api.example.com/users?page=2');

$request = (new Request($uri, 'POST'))
    ->with_header('Content-Type', 'application/json')
    ->with_header('Accept', 'application/json')
    ->with_header('Authorization', 'Bearer my-token');

echo "Method:  " . $request->get_method() . "\n";
echo "URI:     " . $request->get_uri() . "\n";
echo "Headers: " . implode(', ', array_keys($request->get_headers())) . "\n\n";

// --- Immutability: with_method returns a new instance ---
$get_request = $request->with_method('GET');
echo "Original method: " . $request->get_method() . "\n";
echo "New method:      " . $get_request->get_method() . "\n\n";

// --- Build a JSON response ---
$json_response = new JsonResponse(['status' => 'ok', 'count' => 42], 200, [
    'X-Request-ID' => ['req-abc-123'],
]);

echo "Status:       " . $json_response->get_status_code() . "\n";
echo "Content-Type: " . $json_response->get_header_line('Content-Type') . "\n";
echo "Body:         " . (string) $json_response->get_body() . "\n\n";

// --- Text response ---
$text = new TextResponse("Hello, World!\n", 200);
echo "Text body: " . (string) $text->get_body();
