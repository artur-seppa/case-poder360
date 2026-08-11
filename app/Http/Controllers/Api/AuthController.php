<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\IssueTokenPair;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Auth\RefreshAccessToken;
use App\Actions\Auth\RegisterUser;
use App\Actions\Auth\ResetPassword;
use App\Actions\Auth\RevokeAllTokens;
use App\Actions\Auth\SendPasswordResetLink;
use App\Actions\Auth\UpdatePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Api\DeleteAccountRequest;
use App\Http\Requests\Auth\Api\ForgotPasswordRequest;
use App\Http\Requests\Auth\Api\LoginRequest;
use App\Http\Requests\Auth\Api\RefreshTokenRequest;
use App\Http\Requests\Auth\Api\RegisterRequest;
use App\Http\Requests\Auth\Api\ResetPasswordRequest;
use App\Http\Requests\Auth\Api\UpdatePasswordRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    private const REFRESH_TOKEN_COOKIE = 'refresh_token';

    private const REFRESH_TOKEN_COOKIE_PATH = '/api/v1/auth';

    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly LoginUser $loginUser,
        private readonly IssueTokenPair $issueTokenPair,
        private readonly RefreshAccessToken $refreshAccessToken,
        private readonly LogoutUser $logoutUser,
        private readonly RevokeAllTokens $revokeAllTokens,
        private readonly UpdatePassword $updatePassword,
        private readonly SendPasswordResetLink $sendPasswordResetLink,
        private readonly ResetPassword $resetPassword,
    ) {
    }

    /**
     * Cria uma nova conta.
     *
     * Retorna o `access_token` (Bearer) no corpo da resposta e define o
     * cookie httpOnly `refresh_token` (path `/api/v1/auth`).
     *
     * @group Autenticação
     *
     * @unauthenticated
     *
     * @bodyParam name string required O nome do usuário. Example: Ana Beatriz
     * @bodyParam email string required O e-mail, precisa ser único. Example: ana@example.com
     * @bodyParam password string required Mínimo de 8 caracteres. Example: password123
     * @bodyParam password_confirmation string required Precisa repetir a senha. Example: password123
     *
     * @response 422 scenario="E-mail já cadastrado" {
     *   "message": "Os dados enviados são inválidos.",
     *   "errors": {
     *     "email": ["The email has already been taken."]
     *   }
     * }
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerUser->handle($request->toDto());
        $tokens = $this->issueTokenPair->handle($user);

        return $this->tokenResponse($request, $tokens, [
            'user' => UserResource::make($user),
        ], 201);
    }

    /**
     * Autentica com e-mail e senha.
     *
     * @group Autenticação
     *
     * @unauthenticated
     *
     * @bodyParam email string required Example: demo@example.com
     * @bodyParam password string required Example: password
     *
     * @response 422 scenario="Credenciais inválidas" {
     *   "message": "Os dados enviados são inválidos.",
     *   "errors": {
     *     "email": ["As credenciais informadas estão incorretas."]
     *   }
     * }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->loginUser->handle($request->toDto());
        $tokens = $this->issueTokenPair->handle($user);

        return $this->tokenResponse($request, $tokens, [
            'user' => UserResource::make($user),
        ]);
    }

    /**
     * Rotaciona o par de tokens usando o refresh token.
     *
     * Não usa o header `Authorization`. Em vez disso, lê o cookie httpOnly
     * `refresh_token` (definido por `register`/`login`, path `/api/v1/auth`)
     * e retorna um novo `access_token`, rotacionando o cookie.
     *
     * @group Autenticação
     *
     * @unauthenticated
     *
     * @response 200 scenario="Sucesso" {
     *   "access_token": "1|abcdef1234567890",
     *   "token_type": "Bearer",
     *   "expires_in": 900
     * }
     * @response 422 scenario="Cookie refresh_token ausente" {
     *   "message": "Os dados enviados são inválidos.",
     *   "errors": {
     *     "refresh_token": ["The refresh token field is required."]
     *   }
     * }
     * @response 401 scenario="Refresh token inválido, expirado ou já utilizado" {
     *   "message": "Refresh token inválido, expirado ou já utilizado."
     * }
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $result = $this->refreshAccessToken->handle($request->validated('refresh_token'));

        return $this->tokenResponse($request, $result['tokens']);
    }

    /**
     * Revoga o access token atual e limpa o cookie refresh_token.
     *
     * @group Autenticação
     */
    public function logout(Request $request): JsonResponse
    {
        $this->logoutUser->handle($request->user(), $request->cookie(self::REFRESH_TOKEN_COOKIE));

        return response()->json(null, 204)
            ->cookie(cookie()->forget(self::REFRESH_TOKEN_COOKIE, self::REFRESH_TOKEN_COOKIE_PATH));
    }

    /**
     * Troca a senha do usuário autenticado.
     *
     * Revoga todos os tokens de acesso e refresh tokens existentes
     * (incluindo o token usado nesta própria requisição) — é preciso
     * chamar `login` de novo depois.
     *
     * @group Autenticação
     *
     * @bodyParam current_password string required A senha atual. Example: password
     * @bodyParam password string required A nova senha, mínimo de 8 caracteres. Example: nova-senha-123
     * @bodyParam password_confirmation string required Precisa repetir a nova senha. Example: nova-senha-123
     *
     * @response 422 scenario="Senha atual incorreta" {
     *   "message": "Os dados enviados são inválidos.",
     *   "errors": {
     *     "current_password": ["The current password is incorrect."]
     *   }
     * }
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->updatePassword->handle($request->user(), $request->validated('password'));

        return response()->json(null, 204);
    }

    /**
     * Envia um e-mail com o link/token pra resetar a senha.
     *
     * @group Autenticação
     *
     * @unauthenticated
     *
     * @bodyParam email string required Example: demo@example.com
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = $this->sendPasswordResetLink->handle($request->validated('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Os dados enviados são inválidos.',
                'errors' => ['email' => [__($status)]],
            ], 422);
        }

        return response()->json(['message' => __($status)]);
    }

    /**
     * Reseta a senha usando o token recebido por e-mail.
     *
     * Não autentica automaticamente — chame `login` depois com a nova senha.
     *
     * @group Autenticação
     *
     * @unauthenticated
     *
     * @bodyParam token string required O token recebido por e-mail.
     * @bodyParam email string required Example: demo@example.com
     * @bodyParam password string required A nova senha, mínimo de 8 caracteres. Example: nova-senha-123
     * @bodyParam password_confirmation string required Precisa repetir a nova senha. Example: nova-senha-123
     *
     * @response 422 scenario="Token inválido ou expirado" {
     *   "message": "Os dados enviados são inválidos.",
     *   "errors": {
     *     "email": ["This password reset token is invalid."]
     *   }
     * }
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->resetPassword->handle($request->credentials());

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Os dados enviados são inválidos.',
                'errors' => ['email' => [__($status)]],
            ], 422);
        }

        return response()->json(['message' => __($status)]);
    }

    /**
     * Remove a conta do usuário autenticado.
     *
     * Tarefas, tokens de acesso e refresh tokens do usuário são removidos
     * em cascata junto.
     *
     * @group Autenticação
     *
     * @bodyParam password string required A senha atual, pra confirmar. Example: password
     *
     * @response 422 scenario="Senha incorreta" {
     *   "message": "Os dados enviados são inválidos.",
     *   "errors": {
     *     "password": ["The password is incorrect."]
     *   }
     * }
     */
    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();

        // personal_access_tokens is a polymorphic table (no real FK to
        // users), so it doesn't cascade-delete on its own like tasks/
        // refresh_tokens do — has to be cleaned up explicitly.
        $this->revokeAllTokens->handle($user);

        $user->delete();

        return response()->json(null, 204)
            ->cookie(cookie()->forget(self::REFRESH_TOKEN_COOKIE, self::REFRESH_TOKEN_COOKIE_PATH));
    }

    /**
     * Build a JSON response carrying the access token in the body and the
     * refresh token as an httpOnly cookie.
     *
     * @param  array{access_token: string, refresh_token: string, token_type: string, expires_in: int}  $tokens
     * @param  array<string, mixed>  $extra
     */
    private function tokenResponse(Request $request, array $tokens, array $extra = [], int $status = 200): JsonResponse
    {
        return response()->json([
            ...$extra,
            'access_token' => $tokens['access_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
        ], $status)->cookie(
            self::REFRESH_TOKEN_COOKIE,
            $tokens['refresh_token'],
            (int) config('auth.refresh_token_ttl_days') * 60 * 24,
            self::REFRESH_TOKEN_COOKIE_PATH,
            null,
            $request->secure(),
            true,
            false,
            'lax'
        );
    }
}
