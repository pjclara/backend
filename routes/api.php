<?php

use App\Http\Controllers\ExerciseController;
use Illuminate\Support\Facades\Route;

/**
 * API Routes for Exercises
 * 
 * Endpoints:
 * POST   /api/exercises              - Criar novo exercício
 * GET    /api/exercises              - Listar todos os exercícios
 * GET    /api/exercises/{id}         - Obter exercício específico
 * PUT    /api/exercises/{id}         - Atualizar exercício
 * DELETE /api/exercises/{id}         - Deletar exercício
 * 
 * IMPORTANT: The {exercise} parameter MUST be a valid UUID to prevent
 * conflicts with static file requests (e.g., audio files).
 */

Route::apiResource('exercises', ExerciseController::class)
    ->whereUuid('exercise');
