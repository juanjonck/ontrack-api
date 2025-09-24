<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\AnalyticsController;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put("/auth/user", [AuthController::class, "updateUser"]);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Transactions
    Route::apiResource('transactions', TransactionController::class);
    Route::post('/transactions/{transaction}/split', [TransactionController::class, 'split']);

    // Budget
    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::post('/budgets', [BudgetController::class, 'store']);
    Route::get('/budgets/templates', [BudgetController::class, 'getTemplates']);
    Route::post('/budgets/copy-template', [BudgetController::class, 'copyTemplate']);

    // Goals
    Route::apiResource('goals', GoalController::class);
    Route::get('/goals/export', [GoalController::class, 'export']);

    // Categories
    Route::apiResource('categories', CategoryController::class);
    Route::get("/categories/all", [CategoryController::class, "getAllCategories"]);
    Route::post("/categories/selection", [CategoryController::class, "storeUserSelection"]);
    Route::get("/categories/selection/check", [CategoryController::class, "checkSelection"]);

    // Temporary route to seed categories (remove after use)
    Route::get("/categories/seed", [CategoryController::class, "seedCategories"]);

    // Debts
    Route::apiResource('debts', DebtController::class);

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index']);

    // User profile
    Route::get('/user/profile', function (Request $request) {
        return $request->user();
    });
});