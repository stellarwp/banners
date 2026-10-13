<?php declare(strict_types=1);

/**
 * The package runs inside WordPress but the suite does not: Brain\Monkey stubs the handful of core
 * functions it calls, so the tests run with nothing but Composer installed.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// The template exits unless it runs inside WordPress.
define( 'ABSPATH', __DIR__ . '/' );
