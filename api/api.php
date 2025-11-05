<?php
// 1. Headers de CORS y Content-Type para permitir el uso de métodos
// api/api.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Este archivo solo redirige al router
require_once __DIR__ . '/routes.php';
