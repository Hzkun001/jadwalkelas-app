<?php

use App\Controllers\AuthController;
use App\Controllers\ApiController;
use App\Controllers\HomeController;
use App\Controllers\BookingController;
use App\Controllers\AdminController;

// Public web views
Flight::route('GET /', function() {
    $ctrl = new HomeController(Flight::db());
    $ctrl->index();
});

Flight::route('GET /calendar', function() {
    $ctrl = new HomeController(Flight::db());
    $ctrl->calendar();
});

// Booking routes
Flight::route('GET /booking/create', function() {
    $ctrl = new BookingController(Flight::db());
    $ctrl->showCreate();
});

Flight::route('POST /booking/store', function() {
    $ctrl = new BookingController(Flight::db());
    $ctrl->store();
});

Flight::route('GET /booking/list', function() {
    $ctrl = new BookingController(Flight::db());
    $ctrl->listBookings();
});

// Admin panel routes
Flight::route('GET /admin', function() {
    $ctrl = new AdminController(Flight::db());
    $ctrl->dashboard();
});

Flight::route('POST /admin/bookings/@id:[0-9]+/approve', function($id) {
    $ctrl = new AdminController(Flight::db());
    $ctrl->approve((int)$id);
});

Flight::route('POST /admin/bookings/@id:[0-9]+/reject', function($id) {
    $ctrl = new AdminController(Flight::db());
    $ctrl->reject((int)$id);
});

Flight::route('GET /admin/rooms', function() {
    $ctrl = new AdminController(Flight::db());
    $ctrl->rooms();
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
