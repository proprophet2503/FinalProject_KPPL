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
const DB_HOST = 'sql313.infinityfree.com';
const DB_NAME = 'if0_42239138_bantuansosiallokal';
const DB_USER = 'if0_42239138';
const DB_PASS = 'KwwqN11A9tkjy';
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
