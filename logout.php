<?php
require_once __DIR__ . '/includes/init.php';

$_SESSION = [];
session_regenerate_id(true);
set_flash('success', 'Вы вышли из системы.');
redirect('login.php');
