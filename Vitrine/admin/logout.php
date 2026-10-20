<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
session_start();
set_flash('success', 'Você saiu do painel administrativo.');
redirect('admin/login.php');
