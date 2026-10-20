<?php
require_once __DIR__ . '/includes/auth.php';
logout();
session_start();
set_flash('success', 'Você saiu da sua conta.');
redirect('index.php');
