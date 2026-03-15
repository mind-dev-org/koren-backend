<?php

namespace App\Controllers;

use App\Services\AuthService;
use Vine\Core\Request;
use Vine\Core\Response;
use Vine\Support\Validator;

class AuthController
{
    private AuthService $service;

    public function __construct()
    {
        $this->service = new AuthService();
    }

    public function register(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'name'     => 'required|string|min:2|max:100',
            'email'    => 'required|email',
            'password' => 'required|min:8',
        ]);

        if ($v->fails()) {
            return Response::error('VALIDATION_ERROR', 'Validation failed', 422, $v->errors());
        }

        try {
            $result = $this->service->register($request->all());
            return Response::success($result, 201);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'EMAIL_ALREADY_EXISTS') {
                return Response::error('EMAIL_ALREADY_EXISTS', 'This email is already registered', 409);
            }
            throw $e;
        }
    }

    public function login(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($v->fails()) {
            return Response::error('VALIDATION_ERROR', 'Validation failed', 422, $v->errors());
        }

        try {
            $result = $this->service->login($request->input('email'), $request->input('password'));
            return Response::success($result);
        } catch (\RuntimeException $e) {
            return Response::error('INVALID_CREDENTIALS', 'Invalid email or password', 401);
        }
    }

    public function refresh(Request $request): Response
    {
        $token = $request->input('refresh_token');
        if (!$token) {
            return Response::error('REFRESH_TOKEN_INVALID', 'Refresh token is required', 401);
        }

        try {
            $result = $this->service->refresh($token);
            return Response::success($result);
        } catch (\RuntimeException $e) {
            return Response::error('REFRESH_TOKEN_INVALID', 'Invalid or expired refresh token', 401);
        }
    }

    public function logout(Request $request): Response
    {
        $token = $request->input('refresh_token');
        if ($token) {
            $this->service->logout($token);
        }
        return Response::success(['message' => 'Logged out successfully']);
    }

    public function registerFarmer(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'name'      => 'required|string|min:2',
            'email'     => 'required|email',
            'password'  => 'required|min:8',
            'farm_name' => 'required|string',
            'region'    => 'required|string',
        ]);

        if ($v->fails()) {
            return Response::error('VALIDATION_ERROR', 'Validation failed', 422, $v->errors());
        }

        try {
            $result = $this->service->registerFarmer($request->all());
            return Response::success($result, 201);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'EMAIL_ALREADY_EXISTS') {
                return Response::error('EMAIL_ALREADY_EXISTS', 'This email is already registered', 409);
            }
            throw $e;
        }
    }

    public function forgotPassword(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($v->fails()) {
            return Response::error('VALIDATION_ERROR', 'Validation failed', 422, $v->errors());
        }

        $this->service->forgotPassword($request->input('email'));

        return Response::success(['message' => 'If this email is registered, a reset link has been sent']);
    }

    public function resetPassword(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'token'    => 'required|string',
            'password' => 'required|min:8',
        ]);

        if ($v->fails()) {
            return Response::error('VALIDATION_ERROR', 'Validation failed', 422, $v->errors());
        }

        try {
            $this->service->resetPassword($request->input('token'), $request->input('password'));
            return Response::success(['message' => 'Password has been reset successfully']);
        } catch (\RuntimeException $e) {
            return Response::error('TOKEN_INVALID', 'Reset token is invalid or expired', 400);
        }
    }
}
