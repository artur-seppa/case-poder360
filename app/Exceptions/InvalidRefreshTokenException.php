<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidRefreshTokenException extends Exception
{
    public function __construct(string $message = 'Refresh token inválido, expirado ou já utilizado.')
    {
        parent::__construct($message, 401);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 401);
    }
}
