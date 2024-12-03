<?php

// use App\Http\Controllers\EpsController;
// use App\Http\Controllers\GendersController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BibliotecaInscriptionController;
use App\Http\Controllers\ProfesionalsController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\ServiceTypesController;

use Illuminate\Support\Facades\Route;

define("URL", "/{proj_id}/{use_id}/");
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth:sanctum'])->group(function() {
Route::Resource('reservations'.URL, ReservationController::class)->names('reservations')->parameter('','reservations');
// Funciones adicionales ReservationController
Route::get('historial'.URL.'{column}/{data}', [ReservationController::class, "reserFilters"])->name('historial.filters');
Route::get('historialDate'.URL.'{startDate}/{endDate}', [ReservationController::class, "betweenDates"])->name('hi   storial.betweenDates');
Route::get('active/reserv/user'.URL, [ReservationController::class, "activeReservUser"])->name('active.reserv.user');
Route::get('users'.URL, [ReservationController::class, "users"])->name('users');
Route::get('calendar'.URL, [ReservationController::class, "calendar"])->name('calendar');

Route::Resource('spaces'.URL, SpaceController::class)->names('spaces')->parameter('','spaces');

Route::Resource('services'.URL, ServicesController::class)->names('services')->parameter('','services');
// Funciones adicionales ServiceController
Route::Resource('profesionals'.URL, ProfesionalsController::class)->names('profesionals')->parameter('','profesionals');
Route::Resource('service/types'.URL, ServiceTypesController::class)->names('service.types')->parameter('','service_types');

Route::get('historialService'.URL.'{column}/{data}', [ServicesController::class, "reserFilters"])->name('historial.filters');
Route::get('historialServiceDates'.URL.'{startDate}/{endDate}', [ServicesController::class, "betweenDates"])->name('historial.betweenDates');
Route::get('active/service/user'.URL, [ServicesController::class, "ActiveServiceUser"])->name('active.service.user');
Route::get('calendarService'.URL, [ServicesController::class, "calendar"])->name('calendar');
Route::get('profes'.URL, [ProfesionalsController::class, "Profs"])->name('Profs');
Route::Resource('inscriptions'.URL, BibliotecaInscriptionController::class)->names('Inscriptions')->parameter('','inscriptions');
Route::get('inscriptions/actives'.URL .'{id}', [BibliotecaInscriptionController::class, 'actives'])->name('inscriptions.actives');
Route::get('inscriptions/users'.URL .'{id}', [ServicesController::class, 'usersIn'])->name('users.in');
});
