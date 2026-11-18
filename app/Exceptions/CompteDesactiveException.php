<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompteDesactiveException extends Exception
{
    public function render(Request $request): ?JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'error' => 'CompteDesactiveException',
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
