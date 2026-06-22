<?php
/**
 * Konfigurasi aplikasi SiPrioritas Bansos.
 *
 * EDIT bagian DB sesuai panel InfinityFree (menu "MySQL Databases"):
 *   - DB_HOST : contoh "sqlXXX.infinityfree.com"
 *   - DB_NAME : contoh "if0_XXXXXXX_bansos"
 *   - DB_USER : contoh "if0_XXXXXXX"
 *   - DB_PASS : password MySQL dari panel
 *
 * Untuk pengembangan lokal (XAMPP/Laragon) biarkan default di bawah.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
const DB_HOST = 'localhost';
const DB_NAME = 'bansos';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

// ---------------------------------------------------------------------------
// Aplikasi
// ---------------------------------------------------------------------------
const APP_NAME = 'SiPrioritas Bansos';

// Batas validasi domain (selaras UAT).
const MAX_PENDAPATAN = 1000000000.0; // 1 miliar
const MAX_TANGGUNGAN = 20;

// Zona waktu.
date_default_timezone_set('Asia/Jakarta');
