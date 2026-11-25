<?php
require_once __DIR__ . '/includes/config.php';

customer_logout();
flash(t('logged_out'));
redirect('index.php');
