<?php

namespace App\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait VerifiesAccessPassword
{
    protected function denyUnlessAccessPassword(Request $request): ?JsonResponse
    {
        $expected = config('settings.access_password', '');

        if ($expected === '' || $request->input('password') !== $expected) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect password. Access denied.',
            ], 401);
        }

        return null;
    }
}
