<?php

use App\Controllers\AuthController;
use App\Controllers\ApiController;
use App\Controllers\HomeController;

// Public web views
Flight::route('GET /', function() {
    $ctrl = new HomeController(Flight::db());
    $ctrl->index();
});

Flight::route('GET /calendar', function() {
    $ctrl = new HomeController(Flight::db());
    $ctrl->calendar();
});

// Public API endpoints
Flight::route('GET /api/rooms', function() {
    $ctrl = new ApiController(Flight::db());
    Flight::json($ctrl->getRooms());
});

Flight::route('GET /api/check-availability', function() {
    $ctrl = new ApiController(Flight::db());
    Flight::json($ctrl->checkAvailability());
});

Flight::route('GET /api/events', function() {
    $ctrl = new ApiController(Flight::db());
    Flight::json($ctrl->getEvents());
});

Flight::route('POST /api/bookings', function() {
    $ctrl = new ApiController(Flight::db());
    $ctrl->createBooking();
});

// Authentication routes
Flight::route('GET /login', function() {
    $ctrl = new AuthController(Flight::db());
    $ctrl->showLogin();
});

Flight::route('POST /login', function() {
    $ctrl = new AuthController(Flight::db());
    $ctrl->login();
});

Flight::route('GET /logout', function() {
    $ctrl = new AuthController(Flight::db());
    $ctrl->logout();
});
