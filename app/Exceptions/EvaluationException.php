<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EvaluationException extends Exception
{
    protected int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY; // 422 par défaut

    public function __construct(string $message = "", int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY, ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render(Request $request): ?JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'error' => class_basename($this),
            ], $this->statusCode);
        }

        return null;
    }
}
