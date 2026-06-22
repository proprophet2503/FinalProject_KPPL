<?php
require_once __DIR__ . '/lib.php';

logout();
session_start();
flash_set('ok', 'Anda telah keluar.');
redirect('login.php');
